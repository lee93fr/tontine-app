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

        $dueAmounts = $round->payments
            ->map(fn ($payment) => [
                'name' => $payment->user->full_name,
                'amount' => (float) $payment->amount,
            ])
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
