<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_routes_require_authentication(): void
    {
        $this->get('/api/push/status')->assertRedirect(route('login'));
    }

    public function test_user_can_subscribe_to_push_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/push/subscribe', $this->payload())
            ->assertCreated()
            ->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/1'),
            'enabled' => true,
        ]);
    }

    public function test_subscribe_updates_existing_endpoint_without_duplicate(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)->postJson('/api/push/subscribe', $this->payload());
        $this->actingAs($secondUser)->postJson('/api/push/subscribe', $this->payload([
            'keys' => [
                'p256dh' => 'new-public-key',
                'auth' => 'new-auth-token',
            ],
        ]));

        $this->assertSame(1, PushSubscription::count());
        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $secondUser->id,
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/1'),
            'enabled' => true,
        ]);
    }

    public function test_user_can_get_status_and_unsubscribe(): void
    {
        $user = User::factory()->create();

        PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => 'https://push.example.test/subscription/1',
            'endpoint_hash' => hash('sha256', 'https://push.example.test/subscription/1'),
            'p256dh' => 'public-key',
            'auth' => 'auth-token',
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->getJson('/api/push/status')
            ->assertOk()
            ->assertJson([
                'subscribed' => true,
                'subscriptions_count' => 1,
            ]);

        $this->actingAs($user)
            ->postJson('/api/push/unsubscribe', [
                'endpoint' => 'https://push.example.test/subscription/1',
            ])
            ->assertOk()
            ->assertJson([
                'subscribed' => false,
                'disabled' => 1,
            ]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'enabled' => false,
        ]);
    }

    public function test_user_can_trigger_test_push_notification(): void
    {
        $user = User::factory()->create();

        $service = Mockery::mock(PushNotificationService::class);
        $service->shouldReceive('sendPushNotification')
            ->once()
            ->with($user->id, Mockery::on(fn (array $payload) => $payload['title'] === 'Essai'))
            ->andReturn([
                'configured' => true,
                'sent' => 1,
                'failed' => 0,
                'disabled' => 0,
            ]);

        $this->app->instance(PushNotificationService::class, $service);

        $this->actingAs($user)
            ->postJson('/api/push/test', ['title' => 'Essai'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'endpoint' => 'https://push.example.test/subscription/1',
            'keys' => [
                'p256dh' => 'public-key',
                'auth' => 'auth-token',
            ],
        ], $overrides);
    }
}
