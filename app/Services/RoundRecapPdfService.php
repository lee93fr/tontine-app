<?php

namespace App\Services;

use App\Models\Round;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Génère le recap PDF d'un tour : gagnant, montants dus par participant et
 * coordonnées de paiement du trésorier. Le statut de paiement (payé/impayé)
 * n'est volontairement jamais transmis à la vue.
 */
class RoundRecapPdfService
{
    public function download(Round $round): Response
    {
        $round->loadMissing(['winner', 'payments.user', 'tontine']);
        $tontine = $round->tontine;

        $participants = $tontine->participants()->get()->keyBy('id');

        $dueAmounts = $round->payments
            ->map(function ($payment) use ($participants) {
                $slots = (int) ($participants->get($payment->user_id)?->pivot->slots ?? 1);
                $winsCount = (int) ($participants->get($payment->user_id)?->pivot->wins_count ?? 0);

                return [
                    'name' => $payment->user->full_name,
                    'amount' => (float) $payment->amount,
                    'slots' => $slots,
                    'remaining_slots' => max(0, $slots - $winsCount),
                ];
            })
            ->sortBy('name')
            ->values();

        $pdf = Pdf::loadView('pdf.round-recap', [
            'tontine' => $tontine,
            'round' => $round,
            'dueAmounts' => $dueAmounts,
            'paymentInfo' => $tontine->payment_info ?? [],
        ]);

        $filename = sprintf(
            'recap-tour-%d-%s.pdf',
            $round->round_number,
            Str::slug($tontine->name)
        );

        return $pdf->download($filename);
    }
}
