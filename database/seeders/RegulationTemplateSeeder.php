<?php

namespace Database\Seeders;

use App\Models\RegulationTemplate;
use Illuminate\Database\Seeder;

/**
 * Seed du template français — Règlement Tontine MV v3 (28 articles).
 * Pour mettre à jour : php artisan db:seed --class=RegulationTemplateSeeder
 */
class RegulationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        RegulationTemplate::updateOrCreate(
            ['locale' => 'fr', 'is_active' => true],
            [
                'name'    => 'Règlement de tontine — modèle TontineMV v3',
                'version' => 4,
                'blocks'  => $this->blocks(),
            ]
        );
    }

    private function blocks(): array
    {
        return [
            // ── En-tête synthèse ─────────────────────────────────────────────
            [
                'key'       => 'header_table',
                'type'      => 'text',
                'title'     => '',
                'order'     => 10,
                'condition' => null,
                'content'   => <<<HTML
<table class="kv-table">
    <tr><th>Nom de la tontine</th><td>{tontine_name}</td></tr>
    <tr><th>Enchères activées (Oui / Non)</th><td>{has_bidding}</td></tr>
    <tr><th>Avance préliminaire activée (Oui / Non)</th><td>{has_preliminary}</td></tr>
    <tr><th>Pénalités activées (Oui / Non)</th><td>{has_penalties}</td></tr>
    <tr><th>Nombre de tours / participations</th><td>{participations_count}</td></tr>
    <tr><th>Date de démarrage (1er tour)</th><td>{first_round_month_label}</td></tr>
    <tr><th>Montant de l'avance préliminaire</th><td>{preliminary_amount} par participation</td></tr>
    <tr><th>Période de versement de l'avance</th><td>Du {preliminary_period_start} au {preliminary_period_end}</td></tr>
    <tr><th>Cotisation mensuelle</th><td>{cotisation_amount} par participation</td></tr>
    <tr><th>Jour limite de paiement</th><td>Le {payment_day} de chaque mois</td></tr>
    <tr><th>Ouverture des enchères</th><td>Le {bid_day_open} de chaque mois</td></tr>
    <tr><th>Clôture des enchères</th><td>Le {bid_day_close} de chaque mois</td></tr>
    <tr><th>Plafond du taux d'enchère</th><td>{bid_cap} de la cagnotte</td></tr>
    <tr><th>Pénalité de retard / jour</th><td>{penalty_per_day} /jour — plafond {penalty_cap} /mois</td></tr>
    <tr><th>Trésorier</th><td>{tresorier_full_name}</td></tr>
</table>
HTML,
            ],

            [
                'key'       => 'section_1',
                'type'      => 'text',
                'title'     => '1. Objet du document',
                'order'     => 100,
                'condition' => null,
                'content'   => <<<HTML
<p>Le présent document organise le fonctionnement de la tontine « {tontine_name} », constituée entre des participants individuels, des binômes solidaires et, le cas échéant, des participants portant plusieurs tours.</p>
<p>Ce règlement vaut engagement ferme de participation, reconnaissance des obligations financières et acceptation des règles de fonctionnement.</p>
HTML,
            ],

            [
                'key'       => 'section_2',
                'type'      => 'text',
                'title'     => '2. Identification de la tontine',
                'order'     => 200,
                'condition' => null,
                'content'   => <<<HTML
<table class="kv-table">
    <tr><th>Nom de la tontine :</th><td>{tontine_name}</td></tr>
    <tr><th>Nombre de tours / participations :</th><td>{participations_count}</td></tr>
    <tr><th>Date de démarrage :</th><td>{first_round_month_label}</td></tr>
    <tr><th>Avance préliminaire :</th><td>{preliminary_amount} par participation</td></tr>
    <tr><th>Période de versement :</th><td>Du {preliminary_period_start} au {preliminary_period_end}</td></tr>
    <tr><th>Cotisation mensuelle :</th><td>{cotisation_amount} par participation</td></tr>
    <tr><th>Trésorier :</th><td>{tresorier_full_name}</td></tr>
</table>
HTML,
            ],

            [
                'key'       => 'section_3',
                'type'      => 'text',
                'title'     => '3. Trésorier',
                'order'     => 300,
                'condition' => null,
                'content'   => <<<HTML
<p>Le trésorier est <strong>{tresorier_full_name}</strong>. Il est chargé de collecter les avances et cotisations, tenir la caisse commune, organiser les enchères et tirages au sort, redistribuer les sommes au bénéficiaire du tour et tenir le tableau de suivi.</p>
HTML,
            ],

            [
                'key'       => 'section_4',
                'type'      => 'text',
                'title'     => '4. Participants et participations',
                'order'     => 400,
                'condition' => null,
                'content'   => <<<HTML
<p>Les participations sont listées en annexe. Un même participant peut porter plusieurs participations distinctes, chacune correspondant à un tour distinct.</p>
HTML,
            ],

            [
                'key'       => 'section_5_advance',
                'type'      => 'text',
                'title'     => '5. Avance préliminaire',
                'order'     => 500,
                'condition' => 'has_preliminary',
                'content'   => <<<HTML
<p>Chaque participation verse une avance préliminaire de <strong>{preliminary_amount}</strong> dans la caisse commune entre le {preliminary_period_start} et le {preliminary_period_end}.</p>
<p>Pour <strong>{participations_count}</strong> participations, le montant total théorique est {preliminary_amount_int} × {participations_count} = <strong>{advance_total}</strong>, redistribué à raison de <strong>{preliminary_amount}</strong> par tour.</p>
HTML,
            ],

            [
                'key'       => 'section_6_cotisation',
                'type'      => 'text',
                'title'     => '6. Cotisation mensuelle',
                'order'     => 600,
                'condition' => null,
                'content'   => <<<HTML
<p>Chaque participation verse <strong>{cotisation_amount}</strong> par mois, au plus tard le <strong>{payment_day}</strong> de chaque mois, y compris après réception de la cagnotte.</p>
<p>Pour <strong>{participations_count}</strong> participations, la cagnotte mensuelle théorique est {cotisation_amount_int} × {participations_count} = <strong>{pot_amount_total}</strong>.</p>
HTML,
            ],

            [
                'key'       => 'section_7_distribution',
                'type'      => 'text',
                'title'     => '7. Montant distribué à chaque tour',
                'order'     => 700,
                'condition' => null,
                'content'   => <<<HTML
<p>Le bénéficiaire reçoit l'avance du mois (<strong>{preliminary_amount}</strong>) et la cagnotte mensuelle (<strong>{pot_amount_total}</strong>), soit un brut théorique de <strong>{distribution_total}</strong>. Ce montant peut être réduit par l'enchère remportée.</p>
HTML,
            ],

            [
                'key'       => 'section_8_bidding',
                'type'      => 'text',
                'title'     => '8. Système d\'enchères',
                'order'     => 800,
                'condition' => 'has_bidding',
                'content'   => <<<HTML
<p>L'ordre de passage est déterminé par enchères chaque mois. Les enchères s'ouvrent le <strong>{bid_day_open}</strong> du mois et se clôturent le <strong>{bid_day_close}</strong>, après encaissement des cotisations dues au plus tard le {payment_day}.</p>
<p>Le taux d'enchère est plafonné à <strong>{bid_cap}</strong> de la cagnotte (<strong>{pot_amount_total}</strong>), soit au maximum <strong>{bid_max_amount}</strong>.</p>
HTML,
            ],

            [
                'key'       => 'section_9_bidding_effect',
                'type'      => 'text',
                'title'     => '9. Effet financier de l\'enchère',
                'order'     => 900,
                'condition' => 'has_bidding',
                'content'   => <<<HTML
<p>Le gagnant renonce à la portion de cagnotte correspondant au taux proposé. Ce montant est reporté sur le bénéficiaire du tour suivant.</p>
HTML,
            ],

            [
                'key'       => 'section_10_tie_or_no_bid',
                'type'      => 'text',
                'title'     => '10. Égalité ou absence d\'enchère',
                'order'     => 1000,
                'condition' => 'has_bidding',
                'content'   => <<<HTML
<p>En cas d'égalité (ou si aucune enchère n'est formulée), un tirage au sort désigne le bénéficiaire parmi les participations concernées.</p>
HTML,
            ],

            [
                'key'       => 'section_11_binome',
                'type'      => 'text',
                'title'     => '11. Participation solidaire en binôme',
                'order'     => 1100,
                'condition' => 'has_binome',
                'content'   => <<<HTML
<p>Un binôme forme une seule participation collective :</p>
<ul>
    <li>Un seul tour de réception.</li>
    <li>Un seul droit de participation aux enchères.</li>
    <li>Une seule cagnotte à recevoir.</li>
    <li>Une seule obligation de paiement mensuel.</li>
</ul>
<p>Les deux membres s'engagent solidairement : chacun peut être tenu de payer l'intégralité des sommes dues.</p>
HTML,
            ],

            [
                'key'       => 'section_12_binome_payout',
                'type'      => 'text',
                'title'     => '12. Remise de la cagnotte à un binôme',
                'order'     => 1200,
                'condition' => 'has_binome',
                'content'   => <<<HTML
<p>La répartition est libre entre membres. À défaut d'instruction écrite commune, le trésorier peut suspendre la remise jusqu'à accord écrit.</p>
HTML,
            ],

            [
                'key'       => 'section_13_double',
                'type'      => 'text',
                'title'     => '13. Participation multiple par une même personne',
                'order'     => 1300,
                'condition' => 'has_double',
                'content'   => <<<HTML
<p>Chaque participation est traitée de façon autonome. La réception d'un premier tour n'éteint pas l'engagement au titre des participations restantes.</p>
HTML,
            ],

            [
                'key'       => 'section_14_collection',
                'type'      => 'text',
                'title'     => '14. Mode de collecte des fonds',
                'order'     => 1400,
                'condition' => null,
                'content'   => <<<HTML
<p>Le mode privilégié est le virement bancaire. Libellé recommandé : « {tontine_name} – Nom Prénom – Mois concerné ». Pour l'avance : « {tontine_name} – Nom Prénom – Avance ».</p>
HTML,
            ],

            [
                'key'       => 'section_15_virement',
                'type'      => 'text',
                'title'     => '15. Paiement par virement',
                'order'     => 1500,
                'condition' => null,
                'content'   => <<<HTML
<p>Le paiement est réputé effectué à la date de crédit effectif. Le participant anticipe les délais bancaires pour que les fonds arrivent au plus tard le <strong>{payment_day}</strong> de chaque mois.</p>
HTML,
            ],

            [
                'key'       => 'section_16_cash',
                'type'      => 'text',
                'title'     => '16. Espèces, application, chèque',
                'order'     => 1600,
                'condition' => null,
                'content'   => <<<HTML
<p>Ces modes sont acceptés uniquement avec accord exprès du trésorier. Un reçu signé est établi pour tout paiement en espèces.</p>
HTML,
            ],

            [
                'key'       => 'section_17_proof',
                'type'      => 'text',
                'title'     => '17. Preuve du paiement',
                'order'     => 1700,
                'condition' => null,
                'content'   => <<<HTML
<p>Sont acceptés : relevé bancaire, confirmation de virement, reçu signé, confirmation écrite du trésorier. Aucun paiement oral n'est reconnu.</p>
HTML,
            ],

            [
                'key'       => 'section_18_late',
                'type'      => 'text',
                'title'     => '18. Retard de paiement',
                'order'     => 1800,
                'condition' => 'has_penalties',
                'content'   => <<<HTML
<p>Toute cotisation versée après le <strong>{payment_day}</strong> du mois entraîne une pénalité de <strong>{penalty_per_day}</strong> par jour, plafonnée à <strong>{penalty_cap}</strong> par mois. Ces pénalités alimentent la caisse commune.</p>
HTML,
            ],

            [
                'key'       => 'section_19_late_payout',
                'type'      => 'text',
                'title'     => '19. Maintien de la remise en cas de retard',
                'order'     => 1900,
                'condition' => null,
                'content'   => <<<HTML
<p>La remise au bénéficiaire est maintenue. Les sommes manquantes peuvent être avancées par la caisse commune. Le retardataire reste pleinement redevable.</p>
HTML,
            ],

            [
                'key'       => 'section_20_default',
                'type'      => 'text',
                'title'     => '20. Défaut de paiement grave',
                'order'     => 2000,
                'condition' => null,
                'content'   => <<<HTML
<p>Un retard supérieur à 30 jours constitue un défaut grave. Le trésorier peut le constater et en informer les participants.</p>
HTML,
            ],

            [
                'key'       => 'section_21_after_payout',
                'type'      => 'text',
                'title'     => '21. Participant ayant reçu sa cagnotte',
                'order'     => 2100,
                'condition' => null,
                'content'   => <<<HTML
<p>Il reste engagé et doit cotiser jusqu'au dernier tour. En cas de non-paiement après mise en demeure, les sommes dues peuvent porter intérêt à 5 % par mois (dans les limites légales).</p>
HTML,
            ],

            [
                'key'       => 'section_22_not_yet_payout',
                'type'      => 'text',
                'title'     => '22. Participant n\'ayant pas encore reçu sa cagnotte',
                'order'     => 2200,
                'condition' => null,
                'content'   => <<<HTML
<p>Il reste tenu de ses obligations. En cas de défaut grave, son maintien sera discuté entre participants. Les sommes versées ne sont pas remboursables automatiquement.</p>
HTML,
            ],

            [
                'key'       => 'section_23_no_withdrawal',
                'type'      => 'text',
                'title'     => '23. Interdiction de retrait unilatéral',
                'order'     => 2300,
                'condition' => null,
                'content'   => <<<HTML
<p>L'engagement est ferme. Tout remplacement doit être validé par écrit par le trésorier et les autres membres.</p>
HTML,
            ],

            [
                'key'       => 'section_24_caisse',
                'type'      => 'text',
                'title'     => '24. Caisse commune',
                'order'     => 2400,
                'condition' => null,
                'content'   => <<<HTML
<p>Elle est alimentée par les avances, cotisations, pénalités et montants issus des enchères. Elle garantit la remise mensuelle en cas de retard.</p>
HTML,
            ],

            [
                'key'       => 'section_25_transparency',
                'type'      => 'text',
                'title'     => '25. Transparence et suivi',
                'order'     => 2500,
                'condition' => null,
                'content'   => <<<HTML
<p>Le trésorier tient un tableau de suivi (avances, cotisations, retards, bénéficiaires, montants, reports). Chaque participant peut le consulter sur demande.</p>
HTML,
            ],

            [
                'key'       => 'section_26_acknowledgment',
                'type'      => 'text',
                'title'     => '26. Reconnaissance d\'engagement financier',
                'order'     => 2600,
                'condition' => null,
                'content'   => <<<HTML
<p>Par sa signature, le participant reconnaît avoir pris connaissance du règlement et s'engage à régler toutes les sommes dues jusqu'à la fin complète de la tontine.</p>
HTML,
            ],

            [
                'key'       => 'section_27_disputes',
                'type'      => 'text',
                'title'     => '27. Résolution amiable des litiges',
                'order'     => 2700,
                'condition' => null,
                'content'   => <<<HTML
<p>En cas de difficulté, les parties recherchent prioritairement une solution amiable. Le trésorier peut réunir les participants à cet effet.</p>
HTML,
            ],

            [
                'key'       => 'section_28_effective',
                'type'      => 'text',
                'title'     => '28. Entrée en vigueur',
                'order'     => 2800,
                'condition' => null,
                'content'   => <<<HTML
<p>Le règlement entre en vigueur dès sa signature. L'avance est due entre le {preliminary_period_start} et le {preliminary_period_end}. Le premier tour démarre le {first_round_month_label}. La première cotisation est exigible le {first_payment_due_date}. La première enchère se tient le {bid_day_open} du premier mois.</p>
HTML,
            ],

            // ── ANNEXES ──────────────────────────────────────────────────────

            [
                'key'       => 'annexe_1_individual',
                'type'      => 'text',
                'title'     => 'ANNEXE 1 — Reconnaissance individuelle d\'engagement',
                'order'     => 9000,
                'condition' => null,
                'content'   => <<<HTML
<p>Je soussigné(e) :</p>
<table class="kv-table">
    <tr><th colspan="2" style="background:#1d4ed8;color:#fff;text-align:center;">PARTICIPANT</th></tr>
    <tr><th>Nom et prénom :</th><td>&nbsp;</td></tr>
    <tr><th>Date de naissance :</th><td>&nbsp;</td></tr>
    <tr><th>Adresse complète :</th><td>&nbsp;</td></tr>
    <tr><th>Téléphone :</th><td>&nbsp;</td></tr>
    <tr><th>Email :</th><td>&nbsp;</td></tr>
</table>
<br>
<p>Déclare participer à la tontine « {tontine_name} » et reconnais avoir pris connaissance du règlement ci-dessus dans son intégralité.</p>
<p>Je reconnais m'engager à :</p>
<ul>
    <li>verser l'avance initiale de <strong>{preliminary_amount}</strong> selon le calendrier prévu ;</li>
    <li>verser la cotisation mensuelle de <strong>{cotisation_amount}</strong> au plus tard le {payment_day} de chaque mois ;</li>
    <li>payer toute pénalité de retard éventuellement due ;</li>
    <li>régler toutes les sommes restant dues jusqu'à la fin complète de la tontine, y compris si ma cagnotte a déjà été perçue.</li>
</ul>
<br>
<p>Fait à : ..............................................&nbsp;&nbsp;&nbsp;Le : ..............................................</p>
<br>
<p>Mention manuscrite obligatoire :</p>
<p class="mention">« Lu et approuvé, bon pour engagement de participation à la {tontine_name}. »</p>
<p>Signature : ........................................................................</p>
HTML,
            ],

            [
                'key'       => 'annexe_2_binome',
                'type'      => 'text',
                'title'     => 'ANNEXE 2 — Reconnaissance d\'engagement solidaire — Binôme',
                'order'     => 9100,
                'condition' => 'has_binome',
                'content'   => <<<HTML
<p>Nous soussignés :</p>
<table class="kv-table">
    <tr><th colspan="2" style="background:#1d4ed8;color:#fff;text-align:center;">MEMBRE 1</th></tr>
    <tr><th>Nom et prénom :</th><td>&nbsp;</td></tr>
    <tr><th>Date de naissance :</th><td>&nbsp;</td></tr>
    <tr><th>Adresse complète :</th><td>&nbsp;</td></tr>
    <tr><th>Téléphone :</th><td>&nbsp;</td></tr>
    <tr><th>Email :</th><td>&nbsp;</td></tr>
</table>
<table class="kv-table" style="margin-top:12px;">
    <tr><th colspan="2" style="background:#1d4ed8;color:#fff;text-align:center;">MEMBRE 2</th></tr>
    <tr><th>Nom et prénom :</th><td>&nbsp;</td></tr>
    <tr><th>Date de naissance :</th><td>&nbsp;</td></tr>
    <tr><th>Adresse complète :</th><td>&nbsp;</td></tr>
    <tr><th>Téléphone :</th><td>&nbsp;</td></tr>
    <tr><th>Email :</th><td>&nbsp;</td></tr>
</table>
<br>
<p>Déclarons participer à la tontine « {tontine_name} » en qualité de binôme solidaire. Nous formons une seule participation collective.</p>
<p>Nous nous engageons solidairement à régler : avance de <strong>{preliminary_amount}</strong>, cotisation mensuelle de <strong>{cotisation_amount}</strong>, pénalités et toute somme restant due jusqu'à la fin complète de la tontine.</p>
<br>
<p><strong>Modalité de remise de la cagnotte :</strong></p>
<p>☐ Remise intégrale au membre 1&nbsp;&nbsp;&nbsp;☐ Remise intégrale au membre 2&nbsp;&nbsp;&nbsp;☐ Répartition : Membre 1 ___ % / Membre 2 ___ %</p>
<br>
<p><strong>Coordonnées bancaires du membre recevant :</strong> .......................................................................</p>
<br>
<p>Fait à : ..............................................&nbsp;&nbsp;&nbsp;Le : ..............................................</p>
<br>
<p>Mention manuscrite obligatoire — membre 1 :</p>
<p class="mention">« Lu et approuvé, bon pour engagement solidaire à la tontine. »</p>
<p>Signature membre 1 : ........................................................................</p>
<br>
<p>Mention manuscrite obligatoire — membre 2 :</p>
<p class="mention">« Lu et approuvé, bon pour engagement solidaire à la tontine. »</p>
<p>Signature membre 2 : ........................................................................</p>
</div>
HTML,
            ],

            [
                'key'       => 'annexe_3_double',
                'type'      => 'text',
                'title'     => 'ANNEXE 3 — Reconnaissance de participations multiples (double)',
                'order'     => 9200,
                'condition' => 'has_double',
                'content'   => <<<HTML
<p>Je soussigné(e) :</p>
<table class="kv-table">
    <tr><th colspan="2" style="background:#1d4ed8;color:#fff;text-align:center;">PARTICIPANT</th></tr>
    <tr><th>Nom et prénom :</th><td>&nbsp;</td></tr>
    <tr><th>Date de naissance :</th><td>&nbsp;</td></tr>
    <tr><th>Adresse complète :</th><td>&nbsp;</td></tr>
    <tr><th>Téléphone :</th><td>&nbsp;</td></tr>
    <tr><th>Email :</th><td>&nbsp;</td></tr>
</table>
<br>
<p>Reconnais porter plusieurs participations distinctes dans la tontine « {tontine_name} », correspondant à autant de tours distincts.</p>
<p>Je reconnais être tenu(e) de verser :</p>
<ul>
    <li>autant d'avances initiales de <strong>{preliminary_amount}</strong> que de participations ;</li>
    <li>autant de cotisations mensuelles de <strong>{cotisation_amount}</strong> que de participations, jusqu'à la fin complète de la {tontine_name}.</li>
</ul>
<p>Je reconnais que la réception d'un premier tour n'éteint pas mon engagement au titre de mes autres participations, et que je reste tenu(e) de payer toutes les cotisations mensuelles jusqu'au dernier tour, même après avoir perçu l'ensemble de mes cagnottes.</p>
<br>
<p>Fait à : ..............................................&nbsp;&nbsp;&nbsp;Le : ..............................................</p>
<br>
<p>Mention manuscrite obligatoire :</p>
<p class="mention">« Lu et approuvé, bon pour double engagement de participation à la {tontine_name}. »</p>
<p>Signature : ........................................................................</p>
HTML,
            ],
        ];
    }
}
