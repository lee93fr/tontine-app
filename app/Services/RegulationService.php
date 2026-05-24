<?php

namespace App\Services;

use App\Models\RegulationTemplate;
use App\Models\Tontine;
use Illuminate\Support\Str;

/**
 * Construit le règlement final d'une tontine à partir du template global,
 * en évaluant les conditions des blocs et en substituant les variables.
 *
 * Variables disponibles dans le contenu d'un bloc (entre accolades) :
 *
 * — Identité
 *   {tontine_name}                Nom de la tontine
 *   {tresorier_full_name}         Nom + prénom du trésorier
 *   {tresorier_phone}             Téléphone trésorier (si renseigné dans payment_info)
 *
 * — Montants
 *   {cotisation_amount}           "100,00 €" (cotisation mensuelle)
 *   {preliminary_amount}          "800,00 €" (avance initiale par participation)
 *   {max_participants}            "13"
 *   {pot_amount_total}            "1 300 €" (cotisation × participations)
 *   {advance_total}               "10 400 €" (avance × participations)
 *   {bid_cap}                     "15 %"
 *   {bid_max_amount}              "195 €" (bid_cap × pot_amount_total / 100)
 *   {penalty_per_day}             "1,00 €"
 *   {penalty_cap}                 "15,00 €"
 *
 * — Dates
 *   {first_round_month_label}     "1er juillet 2026"
 *   {first_payment_due_date}      "5 juillet 2026"
 *   {first_bid_date}              "10 juillet 2026"
 *   {preliminary_period_start}    "1 juin 2026"
 *   {preliminary_period_end}      "30 juin 2026"
 *   {payment_day}                 "5"
 *   {bid_day_close}               "10"
 *
 * — Annexes auto-générées
 *   {{participants_table}}        Annexe 1 (table HTML des participants)
 *   {{rounds_table}}              Annexe 2 (table HTML des tours)
 *   {{double_participants_names}} "Théo SIV" (liste séparée par virgules)
 *
 * Conditions disponibles dans le champ `condition` d'un bloc :
 *   null           → toujours affiché
 *   'has_bidding'  → tontine->has_bidding = true
 *   'has_penalties'→ tontine->has_penalties = true
 *   'has_binome'   → au moins un participant a un partner
 *   'has_double'   → au moins un participant a slots > 1
 *   'has_preliminary' → tontine->has_preliminary = true
 */
class RegulationService
{
    public function renderHtml(Tontine $tontine, ?array $blocksOverride = null, string $locale = 'fr', bool $forSignature = false): string
    {
        $template = RegulationTemplate::active($locale);
        if (! $template) {
            return '<p>Aucun template de règlement actif pour cette locale.</p>';
        }

        $tontine->loadMissing(['participants', 'rounds']);
        $context = $this->buildContext($tontine);
        $blocks  = $blocksOverride ?: ($template->blocks ?? []);

        usort($blocks, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        $sectionsHtml = '';
        foreach ($blocks as $block) {
            if (! $this->shouldRender($block, $context)) {
                continue;
            }

            $title = $this->substitute($block['title'] ?? '', $context);
            $body  = $this->renderBlockBody($block, $context);
            if (! $body && ! $title) {
                continue;
            }

            $sectionsHtml .= "<section class=\"block block-{$block['type']}\">";
            if ($title !== '') {
                $sectionsHtml .= "<h2 class=\"block-title\">{$title}</h2>";
            }
            $sectionsHtml .= $body;
            $sectionsHtml .= "</section>\n";
        }

        if ($forSignature) {
            $sectionsHtml .= $this->renderSignatureBlock($context);
        }

        return $this->wrapDocument($sectionsHtml, $context);
    }

    /**
     * Bloc de signature avec les balises DocuSeal (signature-field, text-field, date-field).
     * Inséré uniquement lors d'un rendu destiné à l'upload DocuSeal — pas en preview.
     */
    private function renderSignatureBlock(array $context): string
    {
        $tontineName = e($context['tontine_name']);
        return <<<HTML
<section class="block block-docuseal-signature" style="page-break-before: always; margin-top: 40px;">
    <h2 class="block-title">Signature électronique du règlement</h2>
    <p>En signant ci-dessous, je reconnais avoir lu, compris et accepté l'intégralité du présent règlement
    de la <strong>{$tontineName}</strong>, y compris l'ensemble de ses annexes applicables à ma situation
    (participation individuelle, binôme solidaire, ou participation double le cas échéant).</p>
    <p>Je m'engage à respecter toutes les obligations financières prévues : avance initiale, cotisations
    mensuelles, pénalités éventuelles, et toute somme restant due jusqu'à la fin complète de la tontine.</p>

    <div class="signature-zone" style="margin-top: 24px;">
        <p><strong>Nom et prénom du signataire :</strong></p>
        <text-field name="participant_name" role="Participant" required="true" style="display:inline-block; min-width:260px;"></text-field>

        <p style="margin-top: 16px;"><strong>Mention manuscrite obligatoire :</strong>
        <em>« Lu et approuvé, bon pour engagement de participation à la {$tontineName}. »</em></p>
        <text-field name="mention_lu_approuve" role="Participant" required="true" style="display:block; min-width:100%; min-height:60px;"></text-field>

        <p style="margin-top: 16px;"><strong>Date :</strong>
        <date-field name="signature_date" role="Participant" required="true" style="display:inline-block;"></date-field>
        </p>

        <p style="margin-top: 16px;"><strong>Signature :</strong></p>
        <signature-field name="participant_signature" role="Participant" required="true" style="display:block; min-width:300px; min-height:80px;"></signature-field>
    </div>
</section>
HTML;
    }

    /** Construit le contexte de variables résolues pour une tontine. */
    public function buildContext(Tontine $tontine): array
    {
        $participations = (int) $tontine->participants->sum(fn($p) => max(1, (int)($p->pivot->slots ?? 1)));
        $potTotal       = $participations * (float) $tontine->cotisation_amount;
        $advanceTotal   = $participations * (float) ($tontine->preliminary_amount ?? 0);
        $bidMax         = ((float) $tontine->bid_cap / 100) * $potTotal;

        $payment = $tontine->payment_info ?? [];
        $tresorierName = trim(
            ($payment['tresorier_firstname'] ?? '') . ' ' . ($payment['tresorier_name'] ?? '')
        );
        if ($tresorierName === '') {
            $tresorierName = optional($tontine->tresoriers->first())->full_name ?? 'Trésorier à désigner';
        }

        $firstRound = $tontine->first_round_month
            ? \Carbon\Carbon::parse($tontine->first_round_month)
            : null;

        $payDay  = (int) ($tontine->payment_day ?? 5);
        $bidDay  = (int) ($tontine->bid_day_close ?? 10);

        $firstPayDue = $firstRound ? $firstRound->copy()->day($payDay) : null;
        $firstBid    = $firstRound ? $firstRound->copy()->day($bidDay) : null;

        $hasBinome = $tontine->participants->contains(fn($p) => $p->partner_id || $p->partnerOf);
        $hasDouble = $tontine->participants->contains(fn($p) => ($p->pivot->slots ?? 1) > 1);
        $doubleNames = $tontine->participants
            ->filter(fn($p) => ($p->pivot->slots ?? 1) > 1)
            ->map(fn($p) => $p->full_name)
            ->implode(', ');

        return [
            // Identité
            'tontine_name'              => $tontine->name,
            'tresorier_full_name'       => $tresorierName,
            'tresorier_phone'           => $payment['tresorier_phone'] ?? '',
            'tresorier_iban'            => $payment['iban'] ?? '',
            'tresorier_bic'             => $payment['bic'] ?? '',

            // Montants
            'cotisation_amount'         => $this->fmtEur($tontine->cotisation_amount),
            'cotisation_amount_int'     => $this->fmtEurInt($tontine->cotisation_amount),
            'preliminary_amount'        => $this->fmtEur($tontine->preliminary_amount ?? 0),
            'preliminary_amount_int'    => $this->fmtEurInt($tontine->preliminary_amount ?? 0),
            'max_participants'          => (string) $tontine->max_participants,
            'participations_count'      => (string) $participations,
            'pot_amount_total'          => $this->fmtEurInt($potTotal),
            'advance_total'             => $this->fmtEurInt($advanceTotal),
            'distribution_total'        => $this->fmtEurInt($potTotal + ($tontine->preliminary_amount ?? 0)),
            'bid_cap'                   => $tontine->bid_cap . ' %',
            'bid_max_amount'            => $this->fmtEurInt($bidMax),
            'penalty_per_day'           => $tontine->penalty_per_day !== null ? $this->fmtEur($tontine->penalty_per_day) : '—',
            'penalty_cap'               => $tontine->penalty_cap !== null ? $this->fmtEur($tontine->penalty_cap) : '—',

            // Dates
            'first_round_month_label'   => $firstRound ? '1er ' . $this->frenchMonth($firstRound) : '—',
            'first_payment_due_date'    => $firstPayDue ? $firstPayDue->day . ' ' . $this->frenchMonth($firstPayDue) : '—',
            'first_bid_date'            => $firstBid ? $firstBid->day . ' ' . $this->frenchMonth($firstBid) : '—',
            'preliminary_period_start'  => $tontine->preliminary_period_start ? \Carbon\Carbon::parse($tontine->preliminary_period_start)->format('d/m/Y') : '—',
            'preliminary_period_end'    => $tontine->preliminary_period_end ? \Carbon\Carbon::parse($tontine->preliminary_period_end)->format('d/m/Y') : '—',
            'preliminary_period_month'  => $tontine->preliminary_period_start ? $this->frenchMonth(\Carbon\Carbon::parse($tontine->preliminary_period_start)) : '—',
            'payment_day'               => (string) $payDay,
            'bid_day_open'              => (string) ((int) ($tontine->bid_day_open ?? $bidDay)),
            'bid_day_close'             => (string) $bidDay,

            // Flags (utilisés par shouldRender)
            'has_bidding'               => (bool) $tontine->has_bidding,
            'has_penalties'             => (bool) $tontine->has_penalties,
            'has_preliminary'           => (bool) $tontine->has_preliminary,
            'has_binome'                => $hasBinome,
            'has_double'                => $hasDouble,
            'double_participants_names' => $doubleNames,

            // Sera utilisé pour générer les tables d'annexes
            '__tontine'                 => $tontine,
            '__participations'          => $participations,
        ];
    }

    private function shouldRender(array $block, array $context): bool
    {
        $cond = $block['condition'] ?? null;
        if (! $cond) return true;

        return match ($cond) {
            'has_bidding'      => $context['has_bidding'] ?? false,
            'has_penalties'    => $context['has_penalties'] ?? false,
            'has_preliminary'  => $context['has_preliminary'] ?? false,
            'has_binome'       => $context['has_binome'] ?? false,
            'has_double'       => $context['has_double'] ?? false,
            default            => true,
        };
    }

    private function renderBlockBody(array $block, array $context): string
    {
        $type = $block['type'] ?? 'text';

        return match ($type) {
            'annexe_participants' => $this->renderParticipantsTable($context),
            'annexe_rounds'       => $this->renderRoundsTable($context),
            default               => $this->substitute($block['content'] ?? '', $context),
        };
    }

    private function substitute(string $content, array $context): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/i', function ($m) use ($context) {
            $key = $m[1];
            $val = $context[$key] ?? $m[0];
            if (is_bool($val)) return $val ? 'Oui' : 'Non';
            return (string) $val;
        }, $content);
    }

    private function renderParticipantsTable(array $context): string
    {
        $tontine = $context['__tontine'];

        $rows = '';
        $i = 1;
        $alreadyShownPartner = [];

        foreach ($tontine->participants->sortBy('name') as $p) {
            $slots = (int) ($p->pivot->slots ?? 1);
            $partner = $p->partnerOf ?? $p->partner ?? null;
            $isPartnerBlock = ! is_null($partner);

            if ($isPartnerBlock && in_array($partner->id ?? null, $alreadyShownPartner, true)) {
                continue;
            }

            // Première ligne (participation primaire)
            $type = match (true) {
                $slots > 1     => 'Participation double',
                $isPartnerBlock => 'Binôme solidaire',
                default        => 'Individuel',
            };

            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>1</td><td>%s</td><td>%s</td><td></td></tr>',
                $i++,
                e($type),
                e($p->full_name ?: $p->name),
                e($partner->full_name ?? ''),
                e($p->phone ?? ''),
                e($p->email),
                e($context['preliminary_amount']),
                e($context['cotisation_amount'])
            );
            if ($partner) $alreadyShownPartner[] = $partner->id;

            // Si participation double, une seconde ligne
            if ($slots > 1) {
                for ($s = 2; $s <= $slots; $s++) {
                    $rows .= sprintf(
                        '<tr><td>%d</td><td>Participation double</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>1</td><td>%s</td><td>%s</td><td></td></tr>',
                        $i++,
                        e($p->full_name ?: $p->name) . ' (' . $s . 'e part.)',
                        '',
                        e($p->phone ?? ''),
                        e($p->email),
                        e($context['preliminary_amount']),
                        e($context['cotisation_amount'])
                    );
                }
            }
        }

        return '<table class="annexe-table"><thead><tr>'
            . '<th>N°</th><th>Type</th><th>Nom / Prénom 1</th><th>Nom / Prénom 2</th>'
            . '<th>Téléphone</th><th>Email</th><th>Nb part.</th><th>Avance due</th>'
            . '<th>Cotisation mensuelle</th><th>Signature</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function renderRoundsTable(array $context): string
    {
        $tontine = $context['__tontine'];
        $rows = '';
        $count = $context['__participations'] ?? $tontine->max_participants;
        $firstRound = $tontine->first_round_month ? \Carbon\Carbon::parse($tontine->first_round_month) : null;

        for ($t = 1; $t <= $count; $t++) {
            $month = $firstRound ? $firstRound->copy()->addMonths($t - 1) : null;
            $monthLabel = $month ? Str::ucfirst($this->frenchMonth($month)) : '—';
            $payDue = $month ? $month->copy()->day((int)($tontine->payment_day ?? 5))->format('d/m/Y') : '—';
            $bid    = $month ? $month->copy()->day((int)($tontine->bid_day_close ?? 10))->format('d/m/Y') : '—';

            $rows .= "<tr><td>{$t}</td><td>{$monthLabel}</td><td>{$payDue}</td><td>{$bid}</td>"
                  . '<td></td><td></td><td></td><td></td><td></td><td></td></tr>';
        }

        return '<table class="annexe-table"><thead><tr>'
            . '<th>Tour</th><th>Mois</th><th>Date limite paiement</th><th>Date enchère</th>'
            . '<th>Bénéficiaire</th><th>Taux enchère</th><th>Montant enchère</th>'
            . '<th>Montant remis</th><th>Report reçu</th><th>Signature bénéficiaire</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private function wrapDocument(string $body, array $context): string
    {
        $title = e($context['tontine_name']) . ' — Règlement et engagements';
        $css = $this->documentCss();
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>{$css}</style>
</head>
<body>
<header class="doc-header">
    <h1 class="doc-title">RÈGLEMENT ET ENGAGEMENT DE PARTICIPATION<br><span class="doc-name">{$context['tontine_name']}</span></h1>
    <p class="doc-subtitle">Document de fonctionnement, engagement financier, participation solidaire et suivi des tours</p>
</header>
<main>
{$body}
</main>
<footer class="doc-footer">{$title}</footer>
</body>
</html>
HTML;
    }

    private function documentCss(): string
    {
        return <<<CSS
* { box-sizing: border-box; }
body { font-family: 'Calibri', 'Inter', Arial, sans-serif; color: #1f2937; max-width: 820px; margin: 0 auto; padding: 40px 50px; line-height: 1.55; font-size: 14px; }
.doc-header { text-align: center; margin-bottom: 32px; }
.doc-title { font-size: 24px; font-weight: 800; color: #111827; margin: 0 0 6px; }
.doc-name { display: block; font-size: 28px; margin-top: 4px; }
.doc-subtitle { font-style: italic; color: #4b5563; margin: 0; }
section.block { margin: 24px 0; page-break-inside: avoid; }
section.block h2.block-title { color: #1d4ed8; font-size: 16px; font-weight: 700; margin: 24px 0 10px; border: none; }
section.block p { margin: 8px 0; text-align: justify; }
section.block ul { margin: 6px 0 6px 20px; }
section.block strong { color: #111827; }
.kv-table { width: 100%; border-collapse: collapse; margin: 12px 0; }
.kv-table th, .kv-table td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; }
.kv-table th { background: #f3f4f6; text-align: left; font-weight: 600; width: 35%; }
.annexe-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 11px; }
.annexe-table th { background: #dbeafe; border: 1px solid #93c5fd; padding: 6px; font-weight: 700; color: #1e3a8a; text-align: center; }
.annexe-table td { border: 1px solid #cbd5e1; padding: 6px; text-align: center; vertical-align: middle; min-height: 28px; }
.signature-zone { margin-top: 30px; padding: 16px; border: 1px dashed #9ca3af; background: #f9fafb; }
.signature-zone p { margin: 4px 0; }
.mention { font-style: italic; }
.doc-footer { text-align: center; color: #6b7280; font-size: 10px; margin-top: 50px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
@media print { body { padding: 20px; } }
CSS;
    }

    private function fmtEur(?float $v): string
    {
        return number_format((float) $v, 2, ',', ' ') . ' €';
    }

    private function fmtEurInt(?float $v): string
    {
        return number_format((float) $v, 0, ',', ' ') . ' €';
    }

    private function frenchMonth(\Carbon\Carbon $d): string
    {
        $months = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
                   7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
        return $months[$d->month] . ' ' . $d->year;
    }
}
