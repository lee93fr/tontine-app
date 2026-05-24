<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Models\Tontine;
use App\Models\Payment;
use App\Notifications\PaymentReminderNotification;
use App\Notifications\RoundOpenedNotification;
use App\Notifications\RoundResultNotification;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class RoundController extends Controller
{
    public function create(Tontine $tontine, Request $request)
    {
        $nextNumber = $tontine->rounds()->max('round_number') + 1;
        $type = in_array($request->query('type'), ['standard', 'preliminary'])
            ? $request->query('type')
            : 'standard';
        return view('admin.rounds.create', compact('tontine', 'nextNumber', 'type'));
    }

    public function store(Request $request, Tontine $tontine)
    {
        $type = $request->input('type') === 'preliminary' ? 'preliminary' : 'standard';

        if ($type === 'preliminary') {
            $data = $request->validate([
                'round_number'       => 'required|integer|min:1',
                'preliminary_amount' => 'required|numeric|min:1',
                'bid_opens_at'       => 'required|date|before:bid_closes_at',
                'bid_closes_at'      => 'required|date|after:bid_opens_at',
                'notes'              => 'nullable|string',
            ]);

            $participants = $tontine->participants()->get();
            $totalSlots   = $participants->sum(fn($p) => $p->pivot->slots);
            $pot          = $totalSlots * $data['preliminary_amount'];

            $payDue = $tontine->payment_day
                ? \Carbon\Carbon::parse($data['bid_closes_at'])->startOfMonth()->setDay($tontine->payment_day)->toDateString()
                : $data['bid_closes_at'];

            $round = $tontine->rounds()->create([
                'type'               => 'preliminary',
                'round_number'       => $data['round_number'],
                'preliminary_amount' => $data['preliminary_amount'],
                'pot_amount'         => $pot,
                'bid_opens_at'       => $data['bid_opens_at'],
                'bid_closes_at'      => $data['bid_closes_at'],
                'payment_due_at'     => $payDue,
                'notes'              => $data['notes'] ?? null,
                'status'             => 'pending',
            ]);

            foreach ($participants as $participant) {
                Payment::create([
                    'round_id' => $round->id,
                    'user_id'  => $participant->id,
                    'amount'   => $data['preliminary_amount'] * $participant->pivot->slots,
                    'due_date' => $round->payment_due_at,
                    'status'   => 'pending',
                ]);
            }

            return redirect()->route('admin.rounds.show', [$tontine, $round])
                             ->with('success', __('app.round.msg_preliminary'));
        }

        $data = $request->validate([
            'round_number' => 'required|integer|min:1',
            'bid_opens_at' => 'required|date|before:bid_closes_at',
            'bid_closes_at' => 'required|date|after:bid_opens_at',
            'notes' => 'nullable|string',
        ]);

        // La cagnotte tient compte des slots de chaque participant
        $participants = $tontine->participants()->get();
        $totalSlots   = $participants->sum(fn($p) => $p->pivot->slots);

        $stdPayDue = $tontine->payment_day
            ? \Carbon\Carbon::parse($data['bid_closes_at'])->startOfMonth()->setDay($tontine->payment_day)->toDateString()
            : $data['bid_closes_at'];

        $round = $tontine->rounds()->create([
            ...$data,
            'type'           => 'standard',
            'pot_amount'     => 0,
            'payment_due_at' => $stdPayDue,
            'status'         => now()->lt($data['bid_opens_at']) ? 'pending' : 'open',
        ]);

        // Chaque participant paie slots × cotisation (le préliminaire est déjà réglé séparément)
        foreach ($participants as $participant) {
            Payment::create([
                'round_id' => $round->id,
                'user_id'  => $participant->id,
                'amount'   => $tontine->cotisation_amount * $participant->pivot->slots,
                'due_date' => $round->payment_due_at,
                'status'   => 'pending',
            ]);
        }

        // Redistribue le préliminaire équitablement sur tous les tours standards
        $tontine->recalcPotAmounts();

        $participants = $tontine->participants()->with('partnerOf')->get();
        $allRecipients = $participants->flatMap(
            fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p])
        );
        Notification::send($allRecipients, new RoundOpenedNotification($round));

        return redirect()->route('admin.rounds.show', [$tontine, $round])
                         ->with('success', __('app.round.msg_created'));
    }

    public function show(Tontine $tontine, Round $round)
    {
        $round->load(['bids.user', 'winner', 'payments.user', 'tontine']);
        $hasOtherOpenRound = $tontine->rounds()->where('status', 'open')->where('id', '!=', $round->id)->exists();
        return view('admin.rounds.show', compact('tontine', 'round', 'hasOtherOpenRound'));
    }

    public function openRound(Tontine $tontine, Round $round)
    {
        if ($round->status !== 'pending') {
            return back()->with('error', __('app.round.msg_open_error'));
        }

        if ($tontine->rounds()->where('status', 'open')->exists()) {
            return back()->with('error', __('app.round.msg_already_open'));
        }

        $round->update(['status' => 'open']);

        if (!$round->isPreliminary()) {
            $participants = $tontine->participants()->with('partnerOf')->get();
            $allRecipients = $participants->flatMap(
                fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p])
            );
            Notification::send($allRecipients, new RoundOpenedNotification($round));
        }

        return back()->with('success', __('app.round.msg_opened'));
    }

    public function closeRound(Tontine $tontine, Round $round)
    {
        if ($round->status !== 'open') {
            return back()->with('error', __('app.round.msg_close_error'));
        }

        $round->update(['status' => 'closed']);

        return back()->with('success', __('app.round.msg_closed'));
    }

    public function draw(Tontine $tontine, Round $round)
    {
        if ($round->isPreliminary()) {
            return back()->with('error', __('app.round.msg_no_draw_prelim'));
        }

        if (!in_array($round->status, ['open', 'closed'])) {
            return back()->with('error', __('app.round.msg_draw_error'));
        }

        $round->update(['status' => 'closed']);
        $winner = $round->resolveWinner();

        if ($winner) {
            $round->refresh();
            $this->applyWinnerPayments($tontine, $round);

            $participants = $tontine->participants()->with('partnerOf')->get();
            $allRecipients = $participants->flatMap(
                fn($p) => $p->partnerOf ? collect([$p, $p->partnerOf]) : collect([$p])
            );
            Notification::send($allRecipients, new RoundResultNotification($round->fresh(), $winner));
        }

        return redirect()->route('admin.rounds.show', [$tontine, $round])
                         ->with('success', __('app.round.msg_winner', ['name' => $winner?->name]));
    }

    /**
     * Recalcule les montants de paiement après désignation du gagnant :
     *  - Gagnant du tour  → 0 €
     *  - Gagnant passé    → cotisation pleine (100 %)
     *  - Non-encore-gagnant → cotisation × (1 − enchère%)
     *
     * Met également à jour pot_amount avec le montant réel perçu par le gagnant.
     */
    private function applyWinnerPayments(Tontine $tontine, Round $round): void
    {
        $participants  = $tontine->participants()->get();
        $totalSlots    = $participants->sum(fn($p) => $p->pivot->slots);
        $winningBidPct = (float) $round->winning_bid;

        // Isole la part préliminaire déjà incluse dans pot_amount
        $cotisationBase      = max(0, $totalSlots - 1) * (float) $tontine->cotisation_amount;
        $preliminaryPortion  = max(0.0, (float) $round->pot_amount - $cotisationBase);

        $nonWinnerTotal = 0.0;

        foreach ($participants as $participant) {
            $payment = $round->payments()->where('user_id', $participant->id)->first();
            if (!$payment) {
                continue;
            }

            if ($participant->id === $round->winner_id) {
                // wins_count est déjà incrémenté : wins_count - 1 = tours gagnés avant ce tour
                // Le slot gagné CE tour = 0€ ; tours gagnés avant = 100% ; tours restants = (1 - bid%)
                $prevWins       = $participant->pivot->wins_count - 1;
                $remainingSlots = $participant->pivot->slots - $participant->pivot->wins_count;
                $newAmount = round(
                    (float) $tontine->cotisation_amount * ($prevWins + max(0, $remainingSlots) * (1 - $winningBidPct / 100)),
                    2
                );
            } else {
                // Tours déjà gagnés → cotisation pleine (100%)
                // Tours pas encore gagnés → remise de l'enchère appliquée
                $wonSlots       = min($participant->pivot->wins_count, $participant->pivot->slots);
                $remainingSlots = $participant->pivot->slots - $wonSlots;
                $newAmount = round(
                    (float) $tontine->cotisation_amount * ($wonSlots + $remainingSlots * (1 - $winningBidPct / 100)),
                    2
                );
            }

            $updateData = ['amount' => $newAmount];
            if ($newAmount <= 0) {
                $updateData['paid_amount'] = 0;
                $updateData['status']      = 'paid';
                $updateData['paid_at']     = $payment->paid_at ?? now();
            }
            $payment->update($updateData);
            $nonWinnerTotal += $newAmount;
        }

        // pot_amount = ce que le gagnant perçoit réellement
        $round->update(['pot_amount' => round($preliminaryPortion + $nonWinnerTotal, 2)]);
    }

    public function updateDates(Request $request, Tontine $tontine, Round $round)
    {
        $data = $request->validate([
            'bid_closes_at' => 'required|date',
        ]);

        $round->update(['bid_closes_at' => $data['bid_closes_at']]);

        return back()->with('success', 'Date de clôture mise à jour.');
    }

    public function reopenRound(Tontine $tontine, Round $round)
    {
        if ($round->status !== 'closed') {
            return back()->with('error', __('app.round.msg_reopen_error'));
        }

        $round->update(['status' => 'open']);

        return back()->with('success', __('app.round.msg_reopened'));
    }

    public function cancelDraw(Tontine $tontine, Round $round)
    {
        if ($round->status !== 'drawn') {
            return back()->with('error', __('app.round.msg_cancel_draw_error'));
        }

        $winnerId = $round->winner_id;

        // Décrémente wins_count du gagnant
        if ($winnerId) {
            $winner = $tontine->participants()->where('user_id', $winnerId)->first();
            if ($winner) {
                $tontine->participants()->updateExistingPivot($winnerId, [
                    'wins_count' => max(0, $winner->pivot->wins_count - 1),
                ]);
            }
        }

        // Remet les paiements au montant de base (cotisation × slots)
        $participants = $tontine->participants()->get();
        foreach ($participants as $participant) {
            $round->payments()
                ->where('user_id', $participant->id)
                ->update([
                    'amount' => round((float) $tontine->cotisation_amount * $participant->pivot->slots, 2),
                ]);
        }

        // Annule la désignation du gagnant
        $round->update([
            'status'       => 'closed',
            'winner_id'    => null,
            'winning_bid'  => null,
            'drawn_by_lot' => false,
        ]);

        // Recalcule la cagnotte
        $tontine->recalcPotAmounts();

        return back()->with('success', __('app.round.msg_draw_cancelled'));
    }

    public function updatePayment(Request $request, Tontine $tontine, Round $round, Payment $payment)
    {
        if (! $round->isPreliminary() && ! $round->winner_id) {
            return back()->withErrors(['payment' => 'Le gagnant doit être désigné avant de saisir les paiements de cotisations.']);
        }

        $data = $request->validate([
            'paid_amount' => 'nullable|numeric|min:0',
            'status'      => 'nullable|in:pending,paid,partial,late',
            'reference'   => 'nullable|string|max:255',
            'notes'       => 'nullable|string',
        ]);

        if (array_key_exists('paid_amount', $data)) {
            $payment->paid_amount = (float) ($data['paid_amount'] ?? 0);
            $payment->status      = $payment->deriveStatus();
            if ($payment->status === 'paid' && ! $payment->paid_at) {
                $payment->paid_at = now();
            }
            if ($payment->status !== 'paid') {
                $payment->paid_at = null;
            }
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

        return back()->with('success', __('app.round.msg_payment_updated'));
    }

    private const REMINDER_COOLDOWN_HOURS = 24;

    /** Relancer un paiement individuel (email + push) depuis la page admin du round */
    public function remindPayment(Tontine $tontine, Round $round, Payment $payment)
    {
        abort_unless($round->tontine_id === $tontine->id, 404);
        abort_unless($payment->round_id === $round->id, 404);

        if ($payment->status === 'paid') {
            return back()->with('error', 'Ce paiement est déjà réglé.');
        }
        if ($payment->last_reminder_sent_at
            && $payment->last_reminder_sent_at->diffInHours(now()) < self::REMINDER_COOLDOWN_HOURS) {
            return back()->with('error', 'Relance déjà envoyée récemment (cool-down 24h).');
        }

        Notification::send($payment->user, new PaymentReminderNotification($payment));
        $payment->update([
            'last_reminder_sent_at' => now(),
            'reminder_count'        => $payment->reminder_count + 1,
        ]);

        return back()->with('success', "Relance envoyée à {$payment->user->full_name}.");
    }

    /** Relance SMS manuelle (admin-controlled) */
    public function remindPaymentSms(Tontine $tontine, Round $round, Payment $payment, SmsNotificationService $sms)
    {
        abort_unless($round->tontine_id === $tontine->id, 404);
        abort_unless($payment->round_id === $round->id, 404);

        if ($payment->status === 'paid') {
            return back()->with('error', 'Ce paiement est déjà réglé.');
        }

        $phone = $payment->user->phone_number ?? $payment->user->phone;
        if (! $phone) {
            return back()->with('error', "{$payment->user->full_name} n'a pas de numéro de téléphone.");
        }

        $message = (new PaymentReminderNotification($payment))->toSms($payment->user);
        if (! $sms->send($phone, $message)) {
            return back()->with('error', "Échec de l'envoi du SMS (voir les logs / configuration Brevo).");
        }

        $payment->update(['last_sms_reminder_sent_at' => now()]);

        return back()->with('success', "SMS de relance envoyé à {$payment->user->full_name}.");
    }
}
