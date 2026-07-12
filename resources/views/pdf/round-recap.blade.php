<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Récapitulatif du tour #{{ $round->round_number }}</title>
<style>
    @page { margin: 32px 36px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }

    .header { border-bottom: 2px solid #3730a3; padding-bottom: 10px; margin-bottom: 18px; }
    .header .tontine-name { font-size: 18px; font-weight: bold; color: #3730a3; }
    .header .round-title { font-size: 14px; margin-top: 2px; }
    .header .generated-at { font-size: 9px; color: #9ca3af; margin-top: 4px; }

    h2.section-title {
        font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em;
        color: #4338ca; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px;
        margin: 20px 0 8px;
    }

    .winner-box {
        background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 4px;
        padding: 10px 14px; margin-bottom: 4px;
    }
    .winner-box .label { font-size: 9px; text-transform: uppercase; color: #4338ca; }
    .winner-box .name { font-size: 15px; font-weight: bold; }
    .winner-box .details { font-size: 11px; color: #374151; margin-top: 2px; }

    table.due-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.due-table th, table.due-table td {
        border: 1px solid #e5e7eb; padding: 6px 10px; text-align: left; font-size: 11px;
    }
    table.due-table th { background-color: #f3f4f6; text-transform: uppercase; font-size: 9px; color: #6b7280; }
    table.due-table td.amount, table.due-table th.amount { text-align: right; }
    table.due-table td.center, table.due-table th.center { text-align: center; }
    table.due-table tr.is-winner td { background-color: #fefce8; }

    .payment-info { border: 1px solid #e5e7eb; border-radius: 4px; padding: 10px 14px; }
    .payment-info table { width: 100%; border-collapse: collapse; }
    .payment-info td { padding: 3px 0; font-size: 11px; vertical-align: top; }
    .payment-info td.field-label { color: #6b7280; width: 140px; }
    .payment-info td.field-value { font-weight: bold; }

    .footer-note { margin-top: 24px; font-size: 9px; color: #9ca3af; }
</style>
</head>
<body>

    <div class="header">
        <div class="tontine-name">{{ $tontine->name }}</div>
        <div class="round-title">
            Récapitulatif — {{ $round->isPreliminary() ? 'Tour préliminaire' : 'Tour' }} #{{ $round->round_number }}
        </div>
        <div class="generated-at">Généré le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

    @if(!$round->isPreliminary() && $round->winner)
    <h2 class="section-title">Résultat du tour</h2>
    <div class="winner-box">
        <div class="label">Gagnant</div>
        <div class="name">{{ $round->winner->full_name }}</div>
        <div class="details">
            @if($round->drawn_by_lot)
                Désigné par tirage au sort
            @else
                Enchère gagnante : <strong>{{ $round->winning_bid }}%</strong>
            @endif
            — Cagnotte nette perçue : <strong>{{ number_format($round->pot_amount, 2, ',', ' ') }} €</strong>
        </div>
    </div>
    @endif

    <h2 class="section-title">Montants dus pour ce tour</h2>
    <table class="due-table">
        <thead>
            <tr>
                <th>Participant</th>
                <th class="center">Nombre de tours</th>
                <th class="center">Tours restants</th>
                <th class="amount">Montant dû</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dueAmounts as $due)
            <tr class="{{ $round->winner && $due['name'] === $round->winner->full_name ? 'is-winner' : '' }}">
                <td>{{ $due['name'] }}</td>
                <td class="center">{{ $due['slots'] }}</td>
                <td class="center">{{ $due['remaining_slots'] }}</td>
                <td class="amount">{{ number_format($due['amount'], 2, ',', ' ') }} €</td>
            </tr>
            @empty
            <tr><td colspan="4">Aucun paiement enregistré pour ce tour.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Règlement — Coordonnées du trésorier</h2>
    <div class="payment-info">
        @if(empty($paymentInfo))
            <p>Contactez l'administrateur pour connaître les modalités de paiement.</p>
        @else
            <table>
                @php $contact = trim(($paymentInfo['tresorier_firstname'] ?? '').' '.($paymentInfo['tresorier_name'] ?? '')); @endphp
                @if($contact)
                <tr><td class="field-label">Trésorier</td><td class="field-value">{{ $contact }}</td></tr>
                @endif
                @if(!empty($paymentInfo['tresorier_phone']))
                <tr><td class="field-label">Téléphone</td><td class="field-value">{{ $paymentInfo['tresorier_phone'] }}</td></tr>
                @endif
                @if(!empty($paymentInfo['iban']))
                <tr>
                    <td class="field-label">IBAN</td>
                    <td class="field-value">
                        {{ $paymentInfo['iban'] }}
                        @if(!empty($paymentInfo['bic']))
                            &nbsp;—&nbsp;BIC {{ $paymentInfo['bic'] }}
                        @endif
                    </td>
                </tr>
                @endif
                @if(!empty($paymentInfo['revolut_link']))
                <tr><td class="field-label">Revolut</td><td class="field-value">{{ $paymentInfo['revolut_link'] }}</td></tr>
                @endif
                @if(!empty($paymentInfo['address']))
                <tr><td class="field-label">Espèces</td><td class="field-value">{{ $paymentInfo['address'] }}</td></tr>
                @endif
            </table>
        @endif
    </div>

    <div class="footer-note">
        Document généré automatiquement par {{ config('app.name') }} — {{ $tontine->name }}.
    </div>

</body>
</html>
