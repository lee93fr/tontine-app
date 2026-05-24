<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tontine;
use App\Models\Round;
use App\Models\Bid;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        $admin = User::create([
            'name'     => 'Administrateur',
            'email'    => 'admin@tontine.local',
            'password' => Hash::make('password'),
            'role'     => 'admin',
            'active'   => true,
        ]);

        // Participants de démo
        $participants = [];
        foreach (['Alice Dupont', 'Bob Martin', 'Claire Lebrun', 'David Moreau'] as $name) {
            $participants[] = User::create([
                'name'     => $name,
                'email'    => strtolower(str_replace(' ', '.', $name)) . '@exemple.com',
                'password' => Hash::make('password'),
                'role'     => 'participant',
                'active'   => true,
            ]);
        }

        // Tontine de démo
        $tontine = Tontine::create([
            'name'              => 'Tontine Famille 2025',
            'description'       => 'Tontine mensuelle de la famille',
            'cotisation_amount' => 100,
            'max_participants'  => 20,
            'bid_cap'           => 15,
            'status'            => 'active',
        ]);

        // Ajouter les participants
        foreach ($participants as $p) {
            $tontine->participants()->attach($p->id);
        }

        // Tour passé avec résultat
        $pastRound = Round::create([
            'tontine_id'    => $tontine->id,
            'round_number'  => 1,
            'pot_amount'    => 400,
            'bid_opens_at'  => now()->subDays(30),
            'bid_closes_at' => now()->subDays(25),
            'status'        => 'drawn',
            'winner_id'     => $participants[0]->id,
            'winning_bid'   => 10,
            'drawn_by_lot'  => false,
        ]);

        Bid::create(['round_id' => $pastRound->id, 'user_id' => $participants[0]->id, 'amount' => 10, 'bid_at' => now()->subDays(28)]);
        Bid::create(['round_id' => $pastRound->id, 'user_id' => $participants[1]->id, 'amount' => 7, 'bid_at' => now()->subDays(27)]);

        $tontine->participants()->updateExistingPivot($participants[0]->id, ['wins_count' => 1]);

        // Tour en cours ouvert
        $currentRound = Round::create([
            'tontine_id'    => $tontine->id,
            'round_number'  => 2,
            'pot_amount'    => 400,
            'bid_opens_at'  => now()->subHours(2),
            'bid_closes_at' => now()->addDays(3),
            'status'        => 'open',
        ]);

        // Paiements
        foreach ($participants as $p) {
            Payment::create([
                'round_id' => $currentRound->id,
                'user_id'  => $p->id,
                'amount'   => 100,
                'status'   => 'pending',
            ]);
        }

        $this->command->info('✅ Données de démo créées !');
        $this->command->info('Admin : admin@tontine.local / password');
        $this->command->info('Participants : alice.dupont@exemple.com / password (etc.)');
    }
}
