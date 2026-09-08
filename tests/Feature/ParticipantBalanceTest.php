<?php

namespace Tests\Feature;

use App\Models\Tontine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_a_negative_balance_for_one_tontine_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'participant']);
        $firstTontine = $this->createTontine('Première tontine');
        $secondTontine = $this->createTontine('Deuxième tontine');

        $firstTontine->participants()->attach($member->id);
        $secondTontine->participants()->attach($member->id);

        $response = $this->actingAs($admin)->patch(
            route('admin.tontines.participants.balance', [$firstTontine, $member]),
            ['balance' => '-42.75', 'reason' => 'Avance non remboursée']
        );

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tontine_user', [
            'tontine_id' => $firstTontine->id,
            'user_id' => $member->id,
            'balance' => -42.75,
        ]);
        $this->assertDatabaseHas('tontine_user', [
            'tontine_id' => $secondTontine->id,
            'user_id' => $member->id,
            'balance' => 0,
        ]);
        $this->assertDatabaseHas('participant_balance_versions', [
            'tontine_id' => $firstTontine->id,
            'user_id' => $member->id,
            'version' => 1,
            'previous_balance' => 0,
            'new_balance' => -42.75,
            'reason' => 'Avance non remboursée',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_admin_can_store_a_positive_balance(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'participant']);
        $tontine = $this->createTontine('Tontine créditrice');
        $tontine->participants()->attach($member->id);

        $this->actingAs($admin)->patch(
            route('admin.tontines.participants.balance', [$tontine, $member]),
            ['balance' => '125.50']
        )->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tontine_user', [
            'tontine_id' => $tontine->id,
            'user_id' => $member->id,
            'balance' => 125.50,
        ]);
    }

    public function test_balance_cannot_be_updated_for_a_non_member(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'participant']);
        $tontine = $this->createTontine('Tontine sans ce membre');

        $this->actingAs($admin)->patch(
            route('admin.tontines.participants.balance', [$tontine, $member]),
            ['balance' => '10.00']
        )->assertNotFound();
    }

    public function test_each_change_creates_a_version_but_an_unchanged_value_does_not(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'participant']);
        $tontine = $this->createTontine('Tontine versionnée');
        $tontine->participants()->attach($member->id);

        $url = route('admin.tontines.participants.balance', [$tontine, $member]);

        $this->actingAs($admin)->patch($url, ['balance' => '-50'])->assertRedirect();
        $this->actingAs($admin)->patch($url, ['balance' => '-20'])->assertRedirect();
        $this->actingAs($admin)->patch($url, ['balance' => '-20'])->assertRedirect();

        $this->assertDatabaseHas('participant_balance_versions', [
            'tontine_id' => $tontine->id,
            'user_id' => $member->id,
            'version' => 2,
            'previous_balance' => -50,
            'new_balance' => -20,
        ]);
        $this->assertDatabaseCount('participant_balance_versions', 2);
    }

    private function createTontine(string $name): Tontine
    {
        return Tontine::create([
            'name' => $name,
            'cotisation_amount' => 100,
            'max_participants' => 20,
            'bid_cap' => 15,
            'status' => 'active',
        ]);
    }
}
