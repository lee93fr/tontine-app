<?php

namespace App\Http\Controllers\Tresorier;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Round;
use App\Models\Tontine;
use App\Models\User;
use App\Notifications\PaymentReminderNotification;
use App\Services\BidService;
use App\Services\ParticipantBalanceService;
use App\Services\RoundRecapPdfService;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

class TresorierController extends Controller
{
    private function tontine(): Tontine
    {
        $user = auth()->user();

        if ($user->isAdmin() && session()->has('tresorier_tontine_id')) {
            $tontine = Tontine::find(session('tresorier_tontine_id'));
            abort_unless($tontine, 404);
            return $tontine;
        }

        if ($user->isTresorier() && session()->has('tresorier_active_tontine_id')) {
            $tontine = $user->managedTontines()->find(session('tresorier_active_tontine_id'));
            abort_unless($tontine, 404);
            return $tontine;
        }

        abort(404);
    }

    public function dashboard()
    {
        $tontine = $this->tontine();
        $tontine->load(['rounds', 'participants']);

        $activeRoundIds = $tontine->rounds
            ->filter(fn($r) => in_array($r->status, ['drawn', 'paid']) || $r->isPreliminary())
            ->pluck('id');

        $stats = [
            'pending' => Payment::whereIn('round_id', $activeRoundIds)->where('status', 'pending')->count(),
            'late'    => Payment::whereIn('round_id', $activeRoundIds)->where('status', 'late')->count(),
            'paid'    => Payment::whereIn('round_id', $activeRoundIds)->where('status', 'paid')->count(),
            'total'   => Payment::whereIn('round_id', $activeRoundIds)->count(),
        ];

        $openRound = $tontine->rounds->firstWhere('status', 'open');

        return view('tresorier.dashboard', compact('tontine', 'stats', 'openRound'));
    }

    public function showTontine()
    {
        $tontine = $this->tontine();
        $tontine->load(['participants', 'rounds.winner', 'rounds.bids']);
        return view('tresorier.tontine', compact('tontine'));
    }

    public function showRound(Round $round)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        $round->load(['bids.user', 'winner', 'payments.user', 'tontine']);
        $hasOtherOpenRound = $tontine->rounds()
            ->where('status', 'open')
            ->where('id', '!=', $round->id)
            ->exists();
        $eligibleParticipants = $tontine->participants()->get()
            ->filter(fn($p) => $p->pivot->wins_count < $p->pivot->slots);

        return view('tresorier.round', compact('tontine', 'round', 'hasOtherOpenRound', 'eligibleParticipants'));
    }

    public function downloadRecap(Round $round, RoundRecapPdfService $recapPdf)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        return $recapPdf->download($round);
    }

    /** Permet à un trésorier de placer/modifier une enchère pour le compte d'un participant. */
    public function placeBidFor(Request $request, Round $round, User $user, BidService $bidService)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        $cap  = (int) $tontine->bid_cap;
        $data = $request->validate(['amount' => "required|integer|min:0|max:{$cap}"]);

        $result = $bidService->placeBid($round, $user->primaryUser(), (int) $data['amount'], auth()->user());

        if (!$result['success']) {
            if ($result['type'] === 'abort') {
                abort(403, $result['message']);
            }
            return back()->withErrors(['amount' => $result['message']])->withInput();
        }

        return back()->with('success', "Enchère de {$data['amount']}% enregistrée pour {$user->full_name}.");
    }

    /** Permet à un trésorier d'annuler l'enchère d'un participant sur un tour encore ouvert. */
    public function cancelBidFor(Round $round, User $user, BidService $bidService)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        $result = $bidService->cancelBid($round, $user->primaryUser());

        if (!$result['success']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', "Enchère de {$user->full_name} annulée.");
    }

    public function updatePayment(
        Request $request,
        Round $round,
        Payment $payment,
        ParticipantBalanceService $balances,
    )
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);
        abort_unless($payment->round_id === $round->id, 403);
        abort_unless($tontine->participants()->where('user_id', $payment->user_id)->exists(), 403);

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

        // Mode 1 : l'admin saisit un montant payé → on dérive le statut automatiquement
        if (array_key_exists('paid_amount', $data)) {
            $payment->paid_amount = (float) ($data['paid_amount'] ?? 0);
            $payment->status      = $payment->deriveStatus();
            if ($payment->status === 'paid' && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            if ($payment->status !== 'paid') {
                // Reset paid_at si on revient en arrière (statut partial ou pending)
                $payment->paid_at = null;
            }
        }
        // Mode 2 : l'admin force un statut (legacy / cas particuliers)
        elseif (! empty($data['status'])) {
            $payment->status = $data['status'];
            if ($data['status'] === 'paid') {
                $payment->paid_amount = $payment->amount;
                if (! $payment->paid_at) $payment->paid_at = now();
            } elseif ($data['status'] === 'pending' || $data['status'] === 'late') {
                $payment->paid_amount = 0;
                $payment->paid_at = null;
            }
        }

        if (array_key_exists('reference', $data)) {
            $payment->reference = $data['reference'];
        }
        if (array_key_exists('notes', $data)) {
            $payment->notes = $data['notes'];
        }

        $payment->save();

        $newOverpayment = max(0, round((float) $payment->paid_amount - (float) $payment->amount, 2));
        $balances->adjust(
            $tontine,
            $payment->user,
            $request->user(),
            $newOverpayment - $previousOverpayment,
            "Ajustement automatique du trop-perçu — tour #{$round->round_number}, paiement #{$payment->id}.",
        );

        return back()->with('success', __('app.round.msg_payment_updated'));
    }

    public function openRound(Round $round)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        if ($round->status !== 'pending') {
            return back()->with('error', __('app.round.msg_open_error'));
        }

        if ($tontine->rounds()->where('status', 'open')->exists()) {
            return back()->with('error', __('app.round.msg_already_open'));
        }

        $round->update(['status' => 'open']);

        return back()->with('success', __('app.round.msg_opened'));
    }

    public function closeRound(Round $round)
    {
        $tontine = $this->tontine();
        abort_unless($round->tontine_id === $tontine->id, 403);

        if ($round->status !== 'open') {
            return back()->with('error', __('app.round.msg_close_error'));
        }

        $round->update(['status' => 'closed']);

        return back()->with('success', __('app.round.msg_closed'));
    }

    public function paiements(Request $request)
    {
        $tontine = $this->tontine();
        $tontine->load('rounds');

        $query = Payment::with(['user', 'round'])
            ->whereIn('round_id', $tontine->rounds->pluck('id'))
            ->whereHas('round', fn($q) => $q->where(
                fn($q2) => $q2->whereIn('status', ['drawn', 'paid'])->orWhere('type', 'preliminary')
            ));

        if ($request->filled('statut')) {
            $query->where('status', $request->statut);
        }

        if ($request->filled('participant')) {
            $query->where('user_id', $request->participant);
        }

        $payments = $query->orderBy('due_date')->get();

        $participants = $tontine->participants()->get();

        $summary = [
            'pending' => $payments->where('status', 'pending')->count(),
            'late'    => $payments->where('status', 'late')->count(),
            'paid'    => $payments->where('status', 'paid')->count(),
            'partial' => $payments->where('status', 'partial')->count(),
            // Reste à recevoir : somme des (amount - paid_amount) pour tous les paiements non terminés
            'total_due' => $payments
                ->whereIn('status', ['pending', 'late', 'partial'])
                ->sum(fn($p) => max(0, (float) $p->amount - (float) $p->paid_amount)),
            // Encaissé : somme des paid_amount (tous statuts)
            'total_received' => $payments->sum('paid_amount'),
        ];

        return view('tresorier.paiements', compact('tontine', 'payments', 'participants', 'summary'));
    }

    public function enterTresorierMode(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isTresorier(), 403);

        $validated = $request->validate(['tontine_id' => 'required|integer']);
        $tontine = $user->managedTontines()->findOrFail($validated['tontine_id']);

        session(['tresorier_active_tontine_id' => $tontine->id]);

        return redirect()->route('tresorier.dashboard')
                         ->with('success', "Mode trésorier activé : {$tontine->name}");
    }

    public function exitTresorierMode()
    {
        session()->forget('tresorier_active_tontine_id');

        return redirect()->route('participant.dashboard')
                         ->with('success', 'Retour en mode participant.');
    }

    public function switchTontine(Request $request)
    {
        $validated = $request->validate(['tontine_id' => 'required|integer']);

        $user = auth()->user();
        $tontine = $user->managedTontines()->findOrFail($validated['tontine_id']);

        session(['tresorier_active_tontine_id' => $tontine->id]);

        return redirect()->route('tresorier.dashboard')
                         ->with('success', "Tontine active : {$tontine->name}");
    }

    public function editProfile()
    {
        $user = auth()->user();

        return view('tresorier.profile', [
            'user'            => $user,
            'managedTontines' => $user->managedTontines()->orderBy('name')->get(),
            'activeTontineId' => session('tresorier_active_tontine_id'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->merge(['iban' => strtoupper(preg_replace('/\s+/', '', $request->input('iban') ?? ''))]);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'first_name'  => 'required|string|max:255',
            'phone'       => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'city'        => 'required|string|max:255',
            'iban'        => ['nullable', 'string', 'max:34', function ($a, $v, $fail) {
                if (empty($v) || strtoupper(substr($v, 0, 2)) !== 'FR') return;
                if (strlen($v) !== 27) { $fail(__('app.rib.iban_invalid')); return; }
                $r = substr($v, 4).substr($v, 0, 4);
                $n = implode('', array_map(fn($c) => ctype_alpha($c) ? (string)(ord($c)-55) : $c, str_split($r)));
                if (bcmod($n, '97') !== '1') $fail(__('app.rib.iban_invalid'));
            }],
            'bic'         => 'nullable|string|max:11',
        ]);

        auth()->user()->update($data);

        return back()->with('success', __('app.profile.msg_updated'));
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => __('app.profile.err_wrong_password')])->withInput();
        }

        $user->update(['password' => $request->password]);

        return back()->with('success', __('app.profile.msg_password_changed'));
    }

    public function updatePaymentInfo(Request $request)
    {
        $tontine = $this->tontine();

        $data = $request->validate([
            'tresorier_name'      => 'nullable|string|max:100',
            'tresorier_firstname' => 'nullable|string|max:100',
            'tresorier_phone'     => 'nullable|string|max:30',
            'iban'                => 'nullable|string|max:34',
            'bic'                 => 'nullable|string|max:11',
            'address'             => 'nullable|string|max:500',
            'revolut_link'        => 'nullable|string|max:255',
        ]);

        $tontine->update(['payment_info' => array_filter($data, fn($v) => $v !== null && $v !== '')]);

        return back()->with('success', 'Coordonnées de paiement mises à jour.');
    }

    /**
     * Délai anti-spam pour les relances email/push : 1 par 24h par paiement.
     */
    private const REMINDER_COOLDOWN_HOURS = 24;

    /** Relancer un paiement individuel (email + push) */
    public function remindPayment(Payment $payment)
    {
        $tontine = $this->tontine();
        $this->ensurePaymentInScope($tontine, $payment);

        if ($payment->status === 'paid') {
            return back()->with('error', 'Ce paiement est déjà réglé.');
        }

        if ($payment->last_reminder_sent_at
            && $payment->last_reminder_sent_at->diffInHours(now()) < self::REMINDER_COOLDOWN_HOURS) {
            return back()->with('error', "Relance déjà envoyée récemment (cool-down 24h).");
        }

        Notification::send($payment->user, new PaymentReminderNotification($payment));

        $payment->update([
            'last_reminder_sent_at' => now(),
            'reminder_count'        => $payment->reminder_count + 1,
        ]);

        return back()->with('success', "Relance envoyée à {$payment->user->full_name}.");
    }

    /** Relancer tous les paiements en attente / en retard de la tontine */
    public function remindAllPayments()
    {
        $tontine = $this->tontine();

        $payments = Payment::with(['user', 'round'])
            ->whereIn('round_id', $tontine->rounds()->pluck('id'))
            ->whereIn('status', ['pending', 'late'])
            ->whereHas('round', fn($q) => $q->where(
                fn($q2) => $q2->whereIn('status', ['drawn', 'paid'])->orWhere('type', 'preliminary')
            ))
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($payments as $payment) {
            if ($payment->last_reminder_sent_at
                && $payment->last_reminder_sent_at->diffInHours(now()) < self::REMINDER_COOLDOWN_HOURS) {
                $skipped++;
                continue;
            }

            Notification::send($payment->user, new PaymentReminderNotification($payment));
            $payment->update([
                'last_reminder_sent_at' => now(),
                'reminder_count'        => $payment->reminder_count + 1,
            ]);
            $sent++;
        }

        $msg = "{$sent} relance(s) envoyée(s)";
        if ($skipped > 0) {
            $msg .= " · {$skipped} ignorée(s) (cool-down 24h)";
        }
        return back()->with($sent > 0 ? 'success' : 'error', $msg);
    }

    /** Relance SMS manuelle (séparée pour préserver le quota Brevo) */
    public function remindPaymentSms(Payment $payment, SmsNotificationService $sms)
    {
        $tontine = $this->tontine();
        $this->ensurePaymentInScope($tontine, $payment);

        if ($payment->status === 'paid') {
            return back()->with('error', 'Ce paiement est déjà réglé.');
        }

        $phone = $payment->user->phone_number ?? $payment->user->phone;
        if (! $phone) {
            return back()->with('error', "{$payment->user->full_name} n'a pas de numéro de téléphone.");
        }

        $message = (new PaymentReminderNotification($payment))->toSms($payment->user);
        $ok = $sms->send($phone, $message);

        if (! $ok) {
            return back()->with('error', "Échec de l'envoi du SMS (voir les logs / configuration Brevo).");
        }

        $payment->update(['last_sms_reminder_sent_at' => now()]);

        return back()->with('success', "SMS de relance envoyé à {$payment->user->full_name}.");
    }

    private function ensurePaymentInScope(Tontine $tontine, Payment $payment): void
    {
        abort_unless($tontine->rounds()->whereKey($payment->round_id)->exists(), 404);
    }
}
