<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [

            // ── INVITATION ────────────────────────────────────────────────────
            ['key' => 'invitation', 'locale' => 'fr',
             'subject' => 'Invitation à rejoindre la tontine : {tontine_name}',
             'body' => <<<'BODY'
{inviter_name} vous invite à rejoindre la tontine **{tontine_name}**.

**Cotisation mensuelle :** {cotisation_amount} €
**Participants max :** {max_participants}

Cette invitation expire le **{expires_at}**.

Si vous ne souhaitez pas rejoindre cette tontine, ignorez cet email.
BODY],

            ['key' => 'invitation', 'locale' => 'en',
             'subject' => 'Invitation to join the tontine: {tontine_name}',
             'body' => <<<'BODY'
{inviter_name} invites you to join the tontine **{tontine_name}**.

**Monthly contribution:** {cotisation_amount} €
**Max participants:** {max_participants}

This invitation expires on **{expires_at}**.

If you do not wish to join this tontine, please ignore this email.
BODY],

            // ── ENCHÈRE PLACÉE ─────────────────────────────────────────────────
            ['key' => 'bid_placed', 'locale' => 'fr',
             'subject' => '🎯 Nouvelle enchère - Tour #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
Une nouvelle enchère vient d'être placée pour le **Tour #{round_number}** de la tontine **{tontine_name}**.

**Montant de l'enchère :** {amount}%
**Enchère la plus haute actuellement :** {highest}%
**Plafond :** {cap}%

Les enchères se clôturent le **{closes_at}**.

---
**Comment régler votre cotisation :**
{payment_info}
BODY],

            ['key' => 'bid_placed', 'locale' => 'en',
             'subject' => '🎯 New bid - Round #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
A new bid has been placed for **Round #{round_number}** of tontine **{tontine_name}**.

**Bid amount:** {amount}%
**Current highest bid:** {highest}%
**Cap:** {cap}%

Bidding closes on **{closes_at}**.

---
**How to pay your contribution:**
{payment_info}
BODY],

            // ── TOUR OUVERT ────────────────────────────────────────────────────
            ['key' => 'round_opened', 'locale' => 'fr',
             'subject' => '🎯 Enchères ouvertes - Tour #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
Les enchères pour le **Tour #{round_number}** de la tontine **{tontine_name}** sont maintenant ouvertes.

**Cagnotte du tour :** {pot_amount} €
**Clôture des enchères :** {closes_at}
**Plafond d'enchère :** {cap}%
**Versement prévu :** {payout_date}

Si plusieurs participants enchérissent au plafond, un tirage au sort déterminera le gagnant.
Si personne n'enchérit, un tirage au sort est effectué parmi tous les participants éligibles.

---
**Comment régler votre cotisation :**
{payment_info}
BODY],

            ['key' => 'round_opened', 'locale' => 'en',
             'subject' => '🎯 Bidding open - Round #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
Bidding for **Round #{round_number}** of tontine **{tontine_name}** is now open.

**Pot amount:** {pot_amount} €
**Bidding closes:** {closes_at}
**Bid cap:** {cap}%
**Expected payout:** {payout_date}

If multiple participants bid at the cap, a random draw will determine the winner.
If no one bids, a draw is held among all eligible participants.

---
**How to pay your contribution:**
{payment_info}
BODY],

            // ── RÉSULTAT DU TOUR (autres participants) ─────────────────────────
            ['key' => 'round_result', 'locale' => 'fr',
             'subject' => '🏆 Résultats Tour #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
Les résultats du **Tour #{round_number}** de la tontine **{tontine_name}** sont disponibles.

**Gagnant :** {winner_name}
**Cagnotte :** {pot_amount} €
**Mode de désignation :** {method}
**Versement prévu :** {payout_date}

---
**Coordonnées pour régler votre cotisation :**
{payment_info}
BODY],

            ['key' => 'round_result', 'locale' => 'en',
             'subject' => '🏆 Round #{round_number} results | {tontine_name}',
             'body' => <<<'BODY'
The results of **Round #{round_number}** of tontine **{tontine_name}** are now available.

**Winner:** {winner_name}
**Pot:** {pot_amount} €
**Selection method:** {method}
**Expected payout:** {payout_date}

---
**How to pay your contribution:**
{payment_info}
BODY],

            // ── RÉSULTAT DU TOUR (gagnant) ─────────────────────────────────────
            ['key' => 'round_result_winner', 'locale' => 'fr',
             'subject' => '🏆 Félicitations ! Tour #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
🎉 **Félicitations ! Vous avez remporté le Tour #{round_number} de la tontine {tontine_name} !**

**Cagnotte :** {pot_amount} €
**Mode de désignation :** {method}
**Versement prévu le :** {payout_date}

Le trésorier prendra contact avec vous pour procéder au versement.

---
**Coordonnées du trésorier :**
{payment_info}
BODY],

            ['key' => 'round_result_winner', 'locale' => 'en',
             'subject' => '🏆 Congratulations! Round #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
🎉 **Congratulations! You won Round #{round_number} of tontine {tontine_name}!**

**Pot:** {pot_amount} €
**Selection method:** {method}
**Expected payout:** {payout_date}

The treasurer will contact you to arrange the payment.

---
**Treasurer contact details:**
{payment_info}
BODY],

            // ── RAPPEL DE PAIEMENT ─────────────────────────────────────────────
            ['key' => 'payment_reminder', 'locale' => 'fr',
             'subject' => '🔔 Rappel paiement — Tour #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
Nous n'avons pas encore enregistré votre paiement pour le **Tour #{round_number}** de la tontine **{tontine_name}**.

**Montant dû :** {amount}
**Date d'échéance :** {due_date}
**Retard :** {days_late} jour(s)

Merci de procéder au règlement dès que possible afin de ne pas pénaliser le bon déroulement de la tontine.

---
**Coordonnées pour régler votre cotisation :**
{payment_info}

Si vous avez déjà effectué le paiement, merci de transmettre votre justificatif au trésorier pour mise à jour.
BODY],

            ['key' => 'payment_reminder', 'locale' => 'en',
             'subject' => '🔔 Payment reminder — Round #{round_number} | {tontine_name}',
             'body' => <<<'BODY'
We have not yet received your payment for **Round #{round_number}** of tontine **{tontine_name}**.

**Amount due:** {amount}
**Due date:** {due_date}
**Days late:** {days_late}

Please proceed with the payment as soon as possible to avoid disrupting the tontine schedule.

---
**Payment details:**
{payment_info}

If you have already paid, please forward the proof of payment to the treasurer so we can update your status.
BODY],

            // ── DEMANDE DE SIGNATURE ÉLECTRONIQUE ──────────────────────────────
            ['key' => 'signature_request', 'locale' => 'fr',
             'subject' => '✍️ Signature du règlement — {tontine_name}',
             'body' => <<<'BODY'
Vous êtes invité·e à signer électroniquement le **règlement** de la tontine **{tontine_name}**.

Ce document précise le fonctionnement de la tontine, vos engagements financiers et les règles applicables à tous les participants.

**Cotisation mensuelle :** {cotisation_amount}
**Avance initiale :** {preliminary_amount}
**Nombre de participations :** {max_participants}

La signature s'effectue en quelques clics depuis votre navigateur, sans création de compte sur DocuSeal. Pensez à conserver le PDF signé qui vous sera envoyé en confirmation.

⚠️ Ce lien est **personnel et confidentiel** — ne le partagez avec personne.

Si vous avez la moindre question sur le règlement avant de signer, contactez {inviter_name} ou le trésorier de la tontine.
BODY],

            ['key' => 'signature_request', 'locale' => 'en',
             'subject' => '✍️ Sign the regulation — {tontine_name}',
             'body' => <<<'BODY'
You are invited to electronically sign the **regulation** of tontine **{tontine_name}**.

This document outlines how the tontine works, your financial commitments and the rules applicable to all participants.

**Monthly contribution:** {cotisation_amount}
**Initial advance:** {preliminary_amount}
**Number of participations:** {max_participants}

Signing takes only a few clicks directly in your browser — no DocuSeal account needed. A signed PDF will be sent to you as confirmation; please keep it for your records.

⚠️ This link is **personal and confidential** — do not share it.

If you have any questions about the regulation before signing, please contact {inviter_name} or the tontine's treasurer.
BODY],

            // ── RÉINITIALISATION DU MOT DE PASSE ───────────────────────────────
            ['key' => 'password_reset', 'locale' => 'fr',
             'subject' => 'Réinitialisation de votre mot de passe',
             'body' => <<<'BODY'
Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.

Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expire dans **{expire_minutes} minutes**.

Si vous n'avez pas demandé de réinitialisation, vous pouvez ignorer cet email — votre mot de passe restera inchangé.
BODY],

            ['key' => 'password_reset', 'locale' => 'en',
             'subject' => 'Reset your password',
             'body' => <<<'BODY'
You are receiving this email because we received a password reset request for your account.

Click the button below to choose a new password. This link expires in **{expire_minutes} minutes**.

If you did not request a password reset, you can ignore this email — your password will remain unchanged.
BODY],

            // ── CONFIRMATION CHANGEMENT DE MOT DE PASSE ────────────────────────
            ['key' => 'password_changed', 'locale' => 'fr',
             'subject' => 'Votre mot de passe a été modifié',
             'body' => <<<'BODY'
Votre mot de passe a été **réinitialisé avec succès**.

Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.

Si vous n'êtes pas à l'origine de cette modification, contactez-nous immédiatement.
BODY],

            ['key' => 'password_changed', 'locale' => 'en',
             'subject' => 'Your password has been changed',
             'body' => <<<'BODY'
Your password has been **successfully reset**.

You can now log in with your new password.

If you did not make this change, please contact us immediately.
BODY],

        ];

        foreach ($templates as $tpl) {
            EmailTemplate::updateOrCreate(
                ['key' => $tpl['key'], 'locale' => $tpl['locale']],
                ['subject' => $tpl['subject'], 'body' => trim($tpl['body'])]
            );
        }
    }
}
