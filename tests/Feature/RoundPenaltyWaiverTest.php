<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoundPenaltyWaiverTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_disable_and_reenable_penalties_for_a_whole_round(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tontine = Tontine::create([
            'name' => 'Tontine test',
            'cotisation_amount' => 100,
            'max_participants' => 20,
            'bid_cap' => 15,
            'status' => 'active',
        ]);
        $round = $tontine->rounds()->create([
            'type' => 'preliminary',
            'round_number' => 1,
            'pot_amount' => 100,
            'bid_opens_at' => now()->subDays(10),
            'bid_closes_at' => now()->subDays(5),
            'status' => 'closed',
        ]);

        $url = route('admin.rounds.penalties.update', [$tontine, $round]);

        $this->actingAs($admin)->patch($url, ['waive_penalties' => 1])->assertRedirect();
        $this->assertTrue($round->fresh()->waive_penalties);

        $this->actingAs($admin)->patch($url, ['waive_penalties' => 0])->assertRedirect();
        $this->assertFalse($round->fresh()->waive_penalties);
    }

    public function test_round_waiver_overrides_an_individual_payment_penalty(): void
    {
        $payment = new Payment([
            'amount' => 100,
            'paid_amount' => 0,
            'due_date' => now()->subDays(5),
            'waive_penalty' => false,
        ]);

        $this->assertGreaterThan(0, $payment->penaltyAmount(1, 15));
        $this->assertSame(0.0, $payment->penaltyAmount(1, 15, true));
    }
}
