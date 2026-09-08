<?php

namespace App\Http\Controllers\AdminLocal;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Payment;
use App\Models\Round;
use App\Models\Tontine;
use App\Models\User;
use App\Notifications\InvitationNotification;
use App\Notifications\RoundOpenedNotification;
use App\Notifications\RoundResultNotification;
use Carbon\Carbon;
use App\Services\ParticipantSignatureService;
use App\Services\ParticipantBalanceService;
use App\Services\RoundRecapPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class AdminLocalController extends Controller
{
    // ─── Helpers ────────────────────────────────────────────────────────────

    private function myTontines()
    {
        return auth()->user()->adminLocalTontines();
    }

    /** Vérifie que la tontine appartient à cet admin_local (en lecture au moins) */
    private function assertAccess(Tontine $tontine): void
    {
        $exists = DB::table('admin_local_tontines')
            ->where('user_id', auth()->id())
            ->where('tontine_id', $tontine->id)
            ->exists();

        abort_unless($exists, 403, 'Accès non autorisé à cette tontine.');
    }

    /** Vérifie que la tontine est éditable par cet admin_local */
    private function assertOwnership(Tontine $tontine): void
    {
        $canEdit = DB::table('admin_local_tontines')
            ->where('user_id', auth()->id())
            ->where('tontine_id', $tontine->id)
            ->where('can_edit', true)
            ->exists();

        abort_unless($canEdit, 403, 'Vous ne pouvez que consulter cette tontine.');
    }

    private function canEdit(Tontine $tontine): bool
    {
        return DB::table('admin_local_tontines')
            ->where('user_id', auth()->id())
            ->where('tontine_id', $tontine->id)
            ->where('can_edit', true)
            ->exists();
    }

    // ─── Dashboard ──────────────────────────────────────────────────────────

    public function dashboard()
    {
        $tontineIds = $this->myTontines()->pluck('tontines.id');

        $stats = [
            'tontines'        => $tontineIds->count(),
            'own_tontines'    => DB::table('admin_local_tontines')
                ->where('user_id', auth()->id())
                ->where('can_edit', true)
                ->count(),
            'open_rounds'     => Round::whereIn('tontine_id', $tontineIds)->where('status', 'open')->count(),
            'pending_payments'=> Payment::whereHas('round', fn($q) => $q->whereIn('tontine_id', $tontineIds))
                ->where('status', 'pending')
                ->count(),
        ];

        $recentRounds = Round::with(['tontine', 'winner'])
            ->whereIn('tontine_id', $tontineIds)
            ->whereIn('status', ['drawn', 'paid'])
            ->latest()
            ->take(5)
            ->get();

        return view('admin_local.dashboard', compact('stats', 'recentRounds'));
    }

    // ─── Tontines ───────────────────────────────────────────────────────────

    public function tontines()
    {
        $rows = auth()->user()
            ->adminLocalTontines()
            ->withCount('participants')
            ->latest('tontines.created_at')
            ->get()
            ->map(function ($t) {
                $t->is_own = (bool) $t->pivot->can_edit;
                return $t;
            });

        return view('admin_local.tontines.index', compact('rows'));
    }

    public function createTontine()
    {
        return view('admin_local.tontines.create');
    }

    public function storeTontine(Request $request)
    {
        $hasPreliminary = $request->boolean('has_preliminary');
        $hasBidding     = $request->boolean('has_bidding');
        $hasPenalties   = $request->boolean('has_penalties');

        $data = $request->validate([
            'name'                     => 'required|string|max:255',
            'description'              => 'nullable|string',
            'cotisation_amount'        => 'required|numeric|min:1',
            'max_participants'         => 'required|integer|min:2|max:100',
            'bid_cap'                  => 'nullable|integer|min:0|max:100',
            'penalty_per_day'          => 'nullable|numeric|min:0',
            'penalty_cap'              => 'nullable|numeric|min:0',
            'preliminary_amount'       => 'nullable|numeric|min:1',
            'preliminary_period_start' => 'nullable|date',
            'preliminary_period_end'   => 'nullable|date|after_or_equal:preliminary_period_start',
            'first_round_month'        => 'required|date_format:Y-m',
            'bid_day_open'             => 'required|integer|min:1|max:28',
            'bid_day_close'            => 'required|integer|min:1|max:28|gte:bid_day_open',
            'payment_day'              => 'required|integer|min:1|max:28',
            'rounds_count'             => 'nullable|integer|min:1|max:200',
        ]);

        if ($hasPreliminary) {
            $request->validate([
                'preliminary_amount'       => 'required|numeric|min:1',
                'preliminary_period_start' => 'required|date',
                'preliminary_period_end'   => 'required|date|after:preliminary_period_start',
            ]);
        }

        if ($hasBidding) {
            $request->validate(['bid_cap' => 'required|integer|min:1|max:100']);
        }

        $tontine = Tontine::create(array_merge($data, [
            'has_preliminary'        => $hasPreliminary,
            'has_bidding'            => $hasBidding,
            'bid_cap'                => $hasBidding ? ($data['bid_cap'] ?? 15) : 0,
            'bid_requires_signature' => $hasBidding && $request->boolean('bid_requires_signature'),
            'penalty_per_day'        => $hasPenalties ? ($data['penalty_per_day'] ?? 1) : 0,
            'penalty_cap'            => $hasPenalties ? ($data['penalty_cap'] ?? null) : null,
            'first_round_month'      => $data['first_round_month'] . '-01',
        ]));

        // Lier automatiquement au admin_local avec droit d'édition
        DB::table('admin_local_tontines')->insert([
            'user_id'    => auth()->id(),
            'tontine_id' => $tontine->id,
            'can_edit'   => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin-local.tontines.show', $tontine)
                         ->with('success', 'Tontine créée avec succès.');
    }

    public function showTontine(Tontine $tontine)
    {
        $this->assertAccess($tontine);

        $canEdit = $this->canEdit($tontine);
        $tontine->load(['participants', 'rounds.winner', 'rounds.bids', 'balanceVersions.changedBy']);

        $nonMembers = $canEdit
            ? User::where('role', 'participant')
                  ->where('active', true)
                  ->whereDoesntHave('tontines', fn($q) => $q->where('tontine_id', $tontine->id))
                  ->orderBy('name')
                  ->get()
            : collect();

        return view('admin_local.tontines.show', compact('tontine', 'canEdit', 'nonMembers'));
    }

    public function editTontine(Tontine $tontine)
    {
        $this->assertOwnership($tontine);
        $isLaunched = $tontine->rounds()->exists();
        return view('admin_local.tontines.edit', compact('tontine', 'isLaunched'));
    }

    public function updateTontine(Request $request, Tontine $tontine)
    {
        $this->assertOwnership($tontine);
        $isLaunched = $tontine->rounds()->exists();

        if ($isLaunched) {
            $data = $request->validate([
                'name'             => 'required|string|max:255',
                'description'      => 'nullable|string',
                'status'           => 'required|in:active,paused,completed,archived',
                'max_participants' => 'required|integer|min:2|max:100',
                'penalty_cap'      => 'nullable|numeric|min:0',
                'payout_day'       => 'nullable|integer|min:1|max:28',
            ]);
            $tontine->update($data);
        } else {
            $data = $request->validate([
                'name'                     => 'required|string|max:255',
                'description'              => 'nullable|string',
                'cotisation_amount'        => 'required|numeric|min:1',
                'max_participants'         => 'required|integer|min:2|max:100',
                'bid_cap'                  => 'nullable|integer|min:0|max:100',
                'penalty_per_day'          => 'nullable|numeric|min:0',
                'penalty_cap'              => 'nullable|numeric|min:0',
                'first_round_month'        => 'required|date_format:Y-m',
                'bid_day_open'             => 'required|integer|min:1|max:28',
                'bid_day_close'            => 'required|integer|min:1|max:28|gte:bid_day_open',
                'payment_day'              => 'required|integer|min:1|max:28',
                'rounds_count'             => 'nullable|integer|min:1|max:200',
                'status'                   => 'required|in:active,paused,completed,archived',
            ]);
            $hasBidding = $request->boolean('has_bidding');
            $tontine->update(array_merge($data, [
                'has_bidding'            => $hasBidding,
                'bid_requires_signature' => $hasBidding && $request->boolean('bid_requires_signature'),
                'first_round_month'      => $data['first_round_month'] . '-01',
            ]));
        }

        return redirect()->route('admin-local.tontines.show', $tontine)
                         ->with('success', 'Tontine mise à jour.');
    }

    public function generateRoundsAction(Tontine $tontine)
    {
        $this->assertOwnership($tontine);

        if ($tontine->rounds()->exists()) {
            return back()->with('error', 'Des tours ont déjà été générés pour cette tontine.');
        }

        $this->generateRounds($tontine);
        $tontine->recalcPotAmounts();

        return redirect()->route('admin-local.tontines.show', $tontine)
                         ->with('success', 'Tours générés avec succès.');
    }

    private function generateRounds(Tontine $tontine): void
    {
        if ($tontine->has_preliminary && $tontine->preliminary_period_start) {
            $tontine->rounds()->create([
                'type'               => 'preliminary',
                'round_number'       => 0,
                'preliminary_amount' => $tontine->preliminary_amount ?? 0,
                'pot_amount'         => 0,
                'bid_opens_at'       => $tontine->preliminary_period_start->startOfDay(),
                'bid_closes_at'      => $tontine->preliminary_period_end->endOfDay(),
                'payment_due_at'     => $tontine->preliminary_period_end,
                'status'             => 'pending',
            ]);
        }

        $count     = $tontine->rounds_count ?? $tontine->max_participants;
        $baseMonth = Carbon::parse($tontine->first_round_month)->startOfMonth();

        for ($i = 1; $i <= $count; $i++) {
            $month     = $baseMonth->copy()->addMonths($i - 1);
            $bidOpens  = $month->copy()->setDay($tontine->bid_day_open)->startOfDay();
            $bidCloses = $month->copy()->setDay($tontine->bid_day_close)->endOfDay();
            $payDue    = $month->copy()->setDay($tontine->payment_day)->toDateString();

            $tontine->rounds()->create([
                'type'           => 'standard',
                'round_number'   => $i,
                'pot_amount'     => 0,
                'bid_opens_at'   => $bidOpens,
                'bid_closes_at'  => $bidCloses,
                'payment_due_at' => $payDue,
                'status'         => 'pending',
            ]);
        }
    }

    // ─── Invitations ─────────────────────────────────────────────────────────

    public function storeInvitation(Request $request, Tontine $tontine, ParticipantSignatureService $signatures)
    {
        $this->assertOwnership($tontine);

        $data = $request->validate([
            'email'       => 'required|email',
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'nullable|string|max:20',
            'address'     => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city'        => 'nullable|string|max:255',
        ]);

        if ($tontine->participants()->where('email', $data['email'])->exists()) {
            return back()->with('error', 'Cette adresse est déjà membre de la tontine.');
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            $user = User::create([
                'email'       => $data['email'],
                'name'        => $data['name'],
                'first_name'  => $data['first_name'],
                'phone'       => $data['phone'] ?? null,
                'address'     => $data['address'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city'        => $data['city'] ?? null,
                'password'    => Hash::make(Str::random(32)),
                'role'        => 'participant',
                'active'      => false,
            ]);
        }

        $tontine->participants()->syncWithoutDetaching([$user->id]);
        if ($tontine->rounds()->exists()) {
            $tontine->recalcPotAmounts();
        }

        $signatures->ensureSubmission($tontine, $user);

        Invitation::where('tontine_id', $tontine->id)
                  ->where('email', $data['email'])
                  ->whereNull('accepted_at')
                  ->delete();

        $invitation = Invitation::generate($tontine->id, auth()->id(), $data['email']);

        Notification::route('mail', $data['email'])
            ->notify(new InvitationNotification($invitation));

        return back()->with('success', "Invitation envoyée à {$data['email']}. Le compte a été pré-créé.");
    }

    // ─── Participants ────────────────────────────────────────────────────────

    public function addParticipant(Request $request, Tontine $tontine, ParticipantSignatureService $signatures)
    {
        $this->assertOwnership($tontine);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'slots'   => 'required|integer|min:1|max:10',
        ]);

        if ($tontine->participants_locked) {
            return back()->with('error', 'Les participants de cette tontine sont verrouillés.');
        }

        if ($tontine->participants()->count() >= $tontine->max_participants) {
            return back()->with('error', 'Le nombre maximum de participants est atteint.');
        }

        $tontine->participants()->syncWithoutDetaching([
            $request->user_id => ['slots' => $request->slots],
        ]);

        $signatures->ensureSubmission($tontine, User::find($request->user_id));

        foreach ($tontine->rounds()->get() as $round) {
            $exists = Payment::where('round_id', $round->id)
                             ->where('user_id', $request->user_id)
                             ->exists();
            if (!$exists) {
                $unitAmount = $round->isPreliminary()
                    ? ($round->preliminary_amount ?? 0)
                    : $tontine->cotisation_amount;
                Payment::create([
                    'round_id' => $round->id,
                    'user_id'  => $request->user_id,
                    'amount'   => $unitAmount * $request->slots,
                    'due_date' => $round->payment_due_at,
                    'status'   => 'pending',
                ]);
            }
        }

        $tontine->recalcPotAmounts();

        return back()->with('success', 'Participant ajouté.');
    }

    public function toggleParticipantsLock(Tontine $tontine)
    {
        $this->assertOwnership($tontine);

        $tontine->update(['participants_locked' => !$tontine->participants_locked]);

        return back()->with('success', $tontine->participants_locked
            ? 'Participants verrouillés.'
            : 'Participants déverrouillés.');
    }

    public function removeParticipant(Tontine $tontine, User $user)
    {
        $this->assertOwnership($tontine);

        $tontine->participants()->detach($user->id);

        $roundIds = $tontine->rounds()->pluck('id');
        Payment::whereIn('round_id', $roundIds)->where('user_id', $user->id)->delete();

        $tontine->recalcPotAmounts();

        return back()->with('success', 'Participant retiré.');
    }

    public function updateParticipantBalance(
        Request $request,
        Tontine $tontine,
        User $user,
        ParticipantBalanceService $balances,
    )
    {
        $this->assertOwnership($tontine);

        $data = $request->validate([
            'balance' => 'required|numeric|between:-9999999999.99,9999999999.99',
            'reason' => 'nullable|string|max:500',
        ]);

        $changed = $balances->update(
            $tontine,
            $user,
            $request->user(),
            (float) $data['balance'],
            $data['reason'] ?? null,
        );

        return back()->with('success', $changed
            ? "Solde de {$user->full_name} mis à jour et version enregistrée."
            : "Solde de {$user->full_name} inchangé.");
    }

    // ─── Rounds ─────────────────────────────────────────────────────────────

    public function showRound(Tontine $tontine, Round $round)
    {
        $this->assertAccess($tontine);
        $canEdit = $this->canEdit($tontine);
        $round->load(['bids.user', 'winner', 'payments.user', 'tontine']);
        $hasOtherOpenRound = $tontine->rounds()->where('status', 'open')->where('id', '!=', $round->id)->exists();

        return view('admin_local.rounds.show', compact('tontine', 'round', 'canEdit', 'hasOtherOpenRound'));
    }

    public function downloadRecap(Tontine $tontine, Round $round, RoundRecapPdfService $recapPdf)
    {
        $this->assertAccess($tontine);
        abort_unless($round->tontine_id === $tontine->id, 404);

        return $recapPdf->download($round);
    }

    public function openRound(Tontine $tontine, Round $round)
    {
        $this->assertOwnership($tontine);

        if ($round->status !== 'pending') {
            return back()->with('error', 'Ce tour ne peut pas être ouvert.');
        }

        if ($tontine->rounds()->where('status', 'open')->exists()) {
            return back()->with('error', 'Un autre tour est déjà ouvert.');
        }

        $round->update(['status' => 'open']);

        if (!$round->isPreliminary()) {
            $participants  = $tontine->participants()->with('partnerOf')->get();
            $allRecipients = $participants->flatMap(
                fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p])
            );
            Notification::send($allRecipients, new RoundOpenedNotification($round));
        }

        return back()->with('success', 'Tour ouvert.');
    }

    public function closeRound(Tontine $tontine, Round $round)
    {
        $this->assertOwnership($tontine);

        if ($round->status !== 'open') {
            return back()->with('error', 'Ce tour n\'est pas ouvert.');
        }

        $round->update(['status' => 'closed']);

        return back()->with('success', 'Tour fermé.');
    }

    public function reopenRound(Tontine $tontine, Round $round)
    {
        $this->assertOwnership($tontine);

        if ($round->status !== 'closed') {
            return back()->with('error', 'Ce tour n\'est pas fermé.');
        }

        $round->update(['status' => 'open']);

        return back()->with('success', 'Tour réouvert.');
    }

    public function draw(Tontine $tontine, Round $round)
    {
        $this->assertOwnership($tontine);

        if ($round->isPreliminary()) {
            return back()->with('error', 'Impossible de tirer au sort un tour préliminaire.');
        }

        if (!in_array($round->status, ['open', 'closed'])) {
            return back()->with('error', 'Ce tour ne peut pas faire l\'objet d\'un tirage.');
        }

        $round->update(['status' => 'closed']);
        $winner = $round->resolveWinner();

        if ($winner) {
            $round->refresh();
            $this->applyWinnerPayments($tontine, $round);

            $participants  = $tontine->participants()->with('partnerOf')->get();
            $allRecipients = $participants->flatMap(
                fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p])
            );
            Notification::send($allRecipients, new RoundResultNotification($round->fresh(), $winner));
        }

        return redirect()->route('admin-local.rounds.show', [$tontine, $round])
                         ->with('success', 'Vainqueur : ' . ($winner?->full_name ?? '—'));
    }

    public function cancelDraw(Tontine $tontine, Round $round)
    {
        $this->assertOwnership($tontine);

        if ($round->status !== 'drawn') {
            return back()->with('error', 'Impossible d\'annuler ce tirage.');
        }

        $winnerId = $round->winner_id;
        if ($winnerId) {
            $winner = $tontine->participants()->where('user_id', $winnerId)->first();
            if ($winner) {
                $tontine->participants()->updateExistingPivot($winnerId, [
                    'wins_count' => max(0, $winner->pivot->wins_count - 1),
                ]);
            }
        }

        $participants = $tontine->participants()->get();
        foreach ($participants as $participant) {
            $round->payments()
                ->where('user_id', $participant->id)
                ->update([
                    'amount' => round((float) $tontine->cotisation_amount * $participant->pivot->slots, 2),
                ]);
        }

        $round->update([
            'status'       => 'closed',
            'winner_id'    => null,
            'winning_bid'  => null,
            'drawn_by_lot' => false,
        ]);

        $tontine->recalcPotAmounts();

        return back()->with('success', 'Tirage annulé.');
    }

    private function applyWinnerPayments(Tontine $tontine, Round $round): void
    {
        $participants  = $tontine->participants()->get();
        $winningBidPct = (float) $round->winning_bid;

        $cotisationBase     = max(0, $participants->sum(fn($p) => $p->pivot->slots) - 1) * (float) $tontine->cotisation_amount;
        $preliminaryPortion = max(0.0, (float) $round->pot_amount - $cotisationBase);

        $nonWinnerTotal = 0.0;

        foreach ($participants as $participant) {
            $payment = $round->payments()->where('user_id', $participant->id)->first();
            if (!$payment) {
                continue;
            }

            if ($participant->id === $round->winner_id) {
                $prevWins       = $participant->pivot->wins_count - 1;
                $remainingSlots = $participant->pivot->slots - $participant->pivot->wins_count;
                $newAmount = round(
                    (float) $tontine->cotisation_amount * ($prevWins + max(0, $remainingSlots) * (1 - $winningBidPct / 100)),
                    2
                );
            } else {
                $wonSlots       = min($participant->pivot->wins_count, $participant->pivot->slots);
                $remainingSlots = $participant->pivot->slots - $wonSlots;
                $newAmount = round(
                    (float) $tontine->cotisation_amount * ($wonSlots + $remainingSlots * (1 - $winningBidPct / 100)),
                    2
                );
            }

            // Défalque le trop-perçu des tours précédents.
            $credit = Payment::creditBalanceFor($tontine->id, $participant->id, $round->id);
            $newAmount = max(0, round($newAmount - $credit, 2));

            $payment->update(['amount' => $newAmount]);
            $nonWinnerTotal += $newAmount;
        }

        $round->update(['pot_amount' => round($preliminaryPortion + $nonWinnerTotal, 2)]);
    }

    public function updatePayment(
        Request $request,
        Tontine $tontine,
        Round $round,
        Payment $payment,
        ParticipantBalanceService $balances,
    )
    {
        $this->assertOwnership($tontine);
        abort_unless($round->tontine_id === $tontine->id, 404);
        abort_unless($payment->round_id === $round->id, 404);
        abort_unless($tontine->participants()->where('user_id', $payment->user_id)->exists(), 404);

        if (! $round->isPreliminary() && ! $round->winner_id) {
            return back()->withErrors(['payment' => 'Le gagnant doit être désigné avant de saisir les paiements de cotisations.']);
        }

        $data = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:pending,paid,partial,late',
            'reference'   => 'nullable|string|max:255',
            'notes'       => 'nullable|string',
        ]);

        $previousOverpayment = max(0, round((float) $payment->paid_amount - (float) $payment->amount, 2));

        if (array_key_exists('paid_amount', $data)) {
            $payment->paid_amount = (float) ($data['paid_amount'] ?? 0);
            $payment->status      = $payment->deriveStatus();
            if ($payment->status === 'paid' && ! $payment->paid_at) $payment->paid_at = now();
            if ($payment->status !== 'paid') $payment->paid_at = null;
        } elseif (! empty($data['status'])) {
            $payment->status = $data['status'];
            if ($data['status'] === 'paid') {
                $payment->paid_amount = $payment->amount;
                if (! $payment->paid_at) $payment->paid_at = now();
            } elseif (in_array($data['status'], ['pending', 'late'], true)) {
                $payment->paid_amount = 0;
                $payment->paid_at = null;
            }
        }

        if (array_key_exists('reference', $data)) $payment->reference = $data['reference'];
        if (array_key_exists('notes', $data))     $payment->notes = $data['notes'];

        $payment->save();

        $newOverpayment = max(0, round((float) $payment->paid_amount - (float) $payment->amount, 2));
        $balances->adjust(
            $tontine,
            $payment->user,
            $request->user(),
            $newOverpayment - $previousOverpayment,
            "Ajustement automatique du trop-perçu — tour #{$round->round_number}, paiement #{$payment->id}.",
        );

        return back()->with('success', 'Paiement mis à jour.');
    }

    // ─── Profil ─────────────────────────────────────────────────────────────

    public function editProfile()
    {
        return view('admin_local.profile', ['user' => auth()->user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'phone'      => 'nullable|string|max:30',
        ]);

        $user->update($data);

        return back()->with('success', 'Profil mis à jour.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Mot de passe actuel incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Mot de passe mis à jour.');
    }
}
