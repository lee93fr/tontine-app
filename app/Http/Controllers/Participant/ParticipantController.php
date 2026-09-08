<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Round;
use App\Models\Tontine;
use App\Models\User;
use App\Notifications\ParticipantMessageNotification;
use App\Services\BidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class ParticipantController extends Controller
{
    public function dashboard()
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $tontines = $primaryUser->tontines()
                                ->withPivot([
                                    'slots', 'wins_count',
                                    'signature_status', 'signature_signer_url',
                                    'partner_signature_status', 'partner_signature_signer_url',
                                ])
                                ->with(['currentRound'])
                                ->where('status', 'active')
                                ->get();

        $myBids = Bid::where('user_id', $primaryUser->id)
                     ->with('round.tontine')
                     ->latest()
                     ->take(10)
                     ->get();

        $upcomingRounds = Round::whereHas('tontine.participants', fn($q) => $q->where('user_id', $primaryUser->id))
            ->whereIn('status', ['pending', 'open'])
            ->with('tontine')
            ->orderBy('bid_closes_at')
            ->take(5)
            ->get();

        $upcomingPayments = \App\Models\Payment::with(['round.tontine'])
            ->where('user_id', $primaryUser->id)
            ->whereIn('status', ['pending', 'late'])
            ->whereHas('round', fn($q) => $q->where(
                fn($q2) => $q2->whereIn('status', ['drawn', 'paid'])->orWhere('type', 'preliminary')
            ))
            ->orderBy('due_date')
            ->get();

        // Tontines pour lesquelles je suis délégué
        $delegateeIds = $primaryUser->delegatedFor()->pluck('id');
        $delegatedTontines = $delegateeIds->isNotEmpty()
            ? Tontine::whereHas('participants', fn($q) => $q->whereIn('user_id', $delegateeIds))
                     ->where('status', 'active')
                     ->with(['currentRound', 'participants' => fn($q) => $q->whereIn('user_id', $delegateeIds)])
                     ->get()
            : collect();

        return view('participant.dashboard', compact(
            'tontines', 'myBids', 'upcomingRounds', 'upcomingPayments', 'delegatedTontines'
        ));
    }

    public function showTontine(Tontine $tontine)
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $isMember    = $tontine->participants()->where('user_id', $primaryUser->id)->exists();
        $delegateeIds = $primaryUser->delegatedFor()->pluck('id');
        $isDelegateHere = $delegateeIds->isNotEmpty()
            && $tontine->participants()->whereIn('user_id', $delegateeIds)->exists();

        abort_unless($isMember || $isDelegateHere, 403);

        $tontine->load(['rounds' => function ($q) {
            $q->orderByDesc('round_number')->with(['bids.user', 'winner']);
        }, 'participants', 'participants.partnerOf', 'balanceVersions' => function ($q) use ($primaryUser) {
            $q->where('user_id', $primaryUser->id)->with('changedBy')->orderByDesc('version');
        }]);

        $currentRound = $tontine->rounds->firstWhere('status', 'open')
                     ?? $tontine->rounds->firstWhere('status', 'pending');

        $myBidOnCurrent = ($isMember && $currentRound)
            ? Bid::where('round_id', $currentRound->id)->where('user_id', $primaryUser->id)->first()
            : null;

        $myPayments = $isMember
            ? \App\Models\Payment::with('round')
                ->where('user_id', $primaryUser->id)
                ->whereIn('round_id', $tontine->rounds->pluck('id'))
                ->whereHas('round', fn($q) => $q->where(
                    fn($q2) => $q2->whereIn('status', ['drawn', 'paid'])->orWhere('type', 'preliminary')
                ))
                ->orderBy('due_date')
                ->get()
            : collect();

        // Délégués dans cette tontine
        $delegatees = $delegateeIds->isNotEmpty()
            ? User::whereIn('id', $delegateeIds)
                  ->whereHas('tontines', fn($q) => $q->where('tontines.id', $tontine->id))
                  ->get()
                  ->map(function ($delegatee) use ($tontine, $currentRound) {
                      $pivot = $tontine->participants->firstWhere('id', $delegatee->id);
                      return [
                          'user'    => $delegatee,
                          'canBid'  => $pivot && ($pivot->pivot->wins_count < $pivot->pivot->slots),
                          'myBid'   => $currentRound
                              ? Bid::where('round_id', $currentRound->id)->where('user_id', $delegatee->id)->first()
                              : null,
                      ];
                  })
            : collect();

        return view('participant.tontine', compact(
            'tontine', 'currentRound', 'myBidOnCurrent', 'myPayments',
            'isMember', 'delegatees'
        ));
    }

    public function placeBid(Request $request, Round $round, BidService $bidService)
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        // Enchère pour un délégué ?
        $bidAsUserId = $request->input('bid_as_user_id');
        $placedBy    = null;
        if ($bidAsUserId) {
            $delegatee = User::findOrFail($bidAsUserId);
            abort_unless((int) $delegatee->delegate_id === $primaryUser->id, 403);
            $placedBy    = $primaryUser;
            $primaryUser = $delegatee->primaryUser();
        }

        $cap  = (int) $round->tontine->bid_cap;
        $data = $request->validate(['amount' => "required|integer|min:0|max:{$cap}"]);

        $result = $bidService->placeBid($round, $primaryUser, (int) $data['amount'], $placedBy);

        if (!$result['success']) {
            if ($result['type'] === 'abort') {
                abort(403);
            }
            if ($result['type'] === 'validation') {
                return back()->withErrors(['amount' => $result['message']])->withInput();
            }
            return back()->with('error', $result['message']);
        }

        $suffix = $bidAsUserId ? ' (pour ' . User::find($bidAsUserId)->full_name . ')' : '';
        return back()->with('success', __('app.bid.success', ['amount' => $data['amount']]) . $suffix);
    }

    public function editProfile()
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $availableDelegates = User::where('id', '!=', $primaryUser->id)
            ->whereIn('role', ['participant', 'tresorier'])
            ->orderBy('name')
            ->get();

        return view('participant.profile', compact('user', 'primaryUser', 'availableDelegates'));
    }

    public function updateProfile(Request $request)
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', $request->input('iban') ?? ''))]);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city'        => 'required|string|max:255',
            'iban'        => ['nullable', 'string', 'max:34', function($a, $v, $fail) {
                if (empty($v) || strtoupper(substr($v, 0, 2)) !== 'FR') return;
                if (strlen($v) !== 27) { $fail(__('app.rib.iban_invalid')); return; }
                $r = substr($v, 4).substr($v, 0, 4);
                $n = implode('', array_map(fn($c) => ctype_alpha($c) ? (string)(ord($c)-55) : $c, str_split($r)));
                if (bcmod($n, '97') !== '1') $fail(__('app.rib.iban_invalid'));
            }],
            'bic' => 'nullable|string|max:11',
        ]);

        $user->update([
            'name'       => $data['name'],
            'first_name' => $data['first_name'],
            'phone'      => $data['phone'],
        ]);

        $primaryUser->update([
            'address'     => $data['address'],
            'postal_code' => $data['postal_code'],
            'city'        => $data['city'],
            'iban'        => $data['iban'] ?? null,
            'bic'         => $data['bic'] ?? null,
        ]);

        return back()->with('success', __('app.profile.msg_updated'));
    }

    public function updateDelegate(Request $request)
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $request->validate(['delegate_id' => 'nullable|exists:users,id']);

        $delegateId = $request->delegate_id ?: null;
        if ($delegateId && (int) $delegateId === $primaryUser->id) {
            return back()->with('error', 'Vous ne pouvez pas vous déléguer à vous-même.');
        }

        $primaryUser->update(['delegate_id' => $delegateId]);
        $msg = $delegateId
            ? 'Délégation configurée. ' . User::find($delegateId)->full_name . ' peut enchérir pour vous.'
            : 'Délégation supprimée.';

        return back()->with('success', $msg);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.'])->withInput();
        }

        $user->update(['password' => $request->password]);

        return back()->with('success', 'Mot de passe modifié avec succès.');
    }

    public function history()
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        $rounds = Round::whereHas('tontine.participants', fn($q) => $q->where('user_id', $primaryUser->id))
            ->whereIn('status', ['drawn', 'paid'])
            ->with(['tontine', 'winner', 'bids' => fn($q) => $q->where('user_id', $primaryUser->id)])
            ->orderByDesc('bid_closes_at')
            ->paginate(20);

        return view('participant.history', compact('rounds'));
    }

    public function contactAdmin(Request $request, Tontine $tontine)
    {
        $user        = auth()->user();
        $primaryUser = $user->primaryUser();

        abort_unless(
            $tontine->participants()->where('user_id', $primaryUser->id)->exists(),
            403
        );

        $request->validate([
            'recipient_id' => ['required', 'integer'],
            'subject'      => ['required', 'string', 'max:150'],
            'body'         => ['required', 'string', 'max:2000'],
        ]);

        $recipientId = $request->integer('recipient_id');

        $isAdmin = DB::table('admin_local_tontines')
            ->where('tontine_id', $tontine->id)
            ->where('user_id', $recipientId)
            ->exists();
        $isTresorier = $tontine->tresoriers()->where('users.id', $recipientId)->exists();

        abort_unless($isAdmin || $isTresorier, 422, 'Destinataire invalide.');

        $recipient = User::findOrFail($recipientId);

        $recipient->notify(new ParticipantMessageNotification(
            tontine: $tontine,
            sender:  $primaryUser,
            subject: $request->subject,
            body:    $request->body,
        ));

        return back()->with('success', 'Votre message a été envoyé à l\'administrateur.');
    }
}
