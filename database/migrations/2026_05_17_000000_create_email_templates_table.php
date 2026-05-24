<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('locale', 5);
            $table->string('subject');
            $table->text('body');
            $table->timestamps();
            $table->unique(['key', 'locale']);
        });

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }

    private function seed(): void
    {
        $now = now();

        $templates = [
            // ── bid_placed ──────────────────────────────────────────────────
            [
                'key'     => 'bid_placed',
                'locale'  => 'fr',
                'subject' => '🎯 Nouvelle enchère - Tour #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    "Une nouvelle enchère vient d'être placée pour le **Tour #{round_number}** de la tontine **{tontine_name}**.",
                    "**Montant de l'enchère :** {amount}%",
                    "**Enchère la plus haute actuellement :** {highest}%",
                    "**Plafond :** {cap}%",
                    "Les enchères se clôturent le {closes_at}.",
                ]),
            ],
            [
                'key'     => 'bid_placed',
                'locale'  => 'en',
                'subject' => '🎯 New bid - Round #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    'A new bid has been placed for **Round #{round_number}** of the tontine **{tontine_name}**.',
                    '**Bid amount:** {amount}%',
                    '**Current highest bid:** {highest}%',
                    '**Cap:** {cap}%',
                    'Bidding closes on {closes_at}.',
                ]),
            ],

            // ── round_opened ────────────────────────────────────────────────
            [
                'key'     => 'round_opened',
                'locale'  => 'fr',
                'subject' => '🎯 Enchères ouvertes - Tour #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    "Les enchères pour le **Tour #{round_number}** de la tontine **{tontine_name}** sont maintenant ouvertes.",
                    "**Cagnotte du tour :** {pot_amount} €",
                    "**Clôture des enchères :** {closes_at}",
                    "**Plafond d'enchère :** {cap}%",
                    "Si plusieurs participants enchérissent au plafond, un tirage au sort déterminera le gagnant.",
                    "Si personne n'enchérit, un tirage au sort est effectué parmi tous les participants.",
                ]),
            ],
            [
                'key'     => 'round_opened',
                'locale'  => 'en',
                'subject' => '🎯 Bidding open - Round #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    'Bidding for **Round #{round_number}** of the tontine **{tontine_name}** is now open.',
                    '**Round pot:** {pot_amount} €',
                    '**Bidding closes:** {closes_at}',
                    '**Bid cap:** {cap}%',
                    'If multiple participants bid at the cap, a draw will determine the winner.',
                    'If no one bids, a draw is made among all participants.',
                ]),
            ],

            // ── round_result (non-winner) ────────────────────────────────────
            [
                'key'     => 'round_result',
                'locale'  => 'fr',
                'subject' => '🏆 Résultats Tour #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    "Les résultats du Tour #{round_number} de **{tontine_name}** sont disponibles.",
                    "**Gagnant :** {winner_name}",
                    "**Cagnotte :** {pot_amount} €",
                    "**Mode :** {method}",
                ]),
            ],
            [
                'key'     => 'round_result',
                'locale'  => 'en',
                'subject' => '🏆 Round #{round_number} results | {tontine_name}',
                'body'    => implode("\n\n", [
                    'The results for Round #{round_number} of **{tontine_name}** are available.',
                    '**Winner:** {winner_name}',
                    '**Pot:** {pot_amount} €',
                    '**Method:** {method}',
                ]),
            ],

            // ── round_result_winner ──────────────────────────────────────────
            [
                'key'     => 'round_result_winner',
                'locale'  => 'fr',
                'subject' => '🏆 Résultats Tour #{round_number} | {tontine_name}',
                'body'    => implode("\n\n", [
                    "🎉 **Félicitations ! Vous avez remporté le Tour #{round_number} !**",
                    "Cagnotte : **{pot_amount} €**",
                    "Mode de désignation : {method}",
                    "L'administrateur va vous contacter pour le versement.",
                ]),
            ],
            [
                'key'     => 'round_result_winner',
                'locale'  => 'en',
                'subject' => '🏆 Round #{round_number} results | {tontine_name}',
                'body'    => implode("\n\n", [
                    '🎉 **Congratulations! You won Round #{round_number}!**',
                    'Pot: **{pot_amount} €**',
                    'Method: {method}',
                    'The administrator will contact you for payment.',
                ]),
            ],

            // ── invitation ───────────────────────────────────────────────────
            [
                'key'     => 'invitation',
                'locale'  => 'fr',
                'subject' => 'Invitation à rejoindre la tontine : {tontine_name}',
                'body'    => implode("\n\n", [
                    "{inviter_name} vous invite à rejoindre la tontine **{tontine_name}**.",
                    "**Cotisation mensuelle :** {cotisation_amount} €",
                    "**Participants max :** {max_participants}",
                    "Cette invitation expire le **{expires_at}**.",
                    "Si vous ne souhaitez pas rejoindre cette tontine, ignorez cet email.",
                ]),
            ],
            [
                'key'     => 'invitation',
                'locale'  => 'en',
                'subject' => 'Invitation to join the tontine: {tontine_name}',
                'body'    => implode("\n\n", [
                    '{inviter_name} invites you to join the tontine **{tontine_name}**.',
                    '**Monthly contribution:** {cotisation_amount} €',
                    '**Max participants:** {max_participants}',
                    'This invitation expires on **{expires_at}**.',
                    'If you do not wish to join this tontine, please ignore this email.',
                ]),
            ],
        ];

        foreach ($templates as $tpl) {
            DB::table('email_templates')->insert(array_merge($tpl, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }
};
