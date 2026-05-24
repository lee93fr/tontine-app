<?php

namespace Tests\Feature;

use App\Channels\SmsChannel;
use App\Models\User;
use App\Notifications\AccountEventNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Méthodes du modèle User ──────────────────────────────────────

    public function test_wants_email_returns_true_when_enabled(): void
    {
        $user = User::factory()->create(['notify_by_email' => true]);
        $this->assertTrue($user->wantsEmailNotifications());
    }

    public function test_wants_email_returns_false_when_disabled(): void
    {
        $user = User::factory()->create(['notify_by_email' => false]);
        $this->assertFalse($user->wantsEmailNotifications());
    }

    public function test_wants_sms_returns_true_when_enabled_and_phone_verified(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => true,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => now(),
        ]);
        $this->assertTrue($user->wantsSmsNotifications());
    }

    public function test_wants_sms_returns_false_when_disabled(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => false,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => now(),
        ]);
        $this->assertFalse($user->wantsSmsNotifications());
    }

    public function test_wants_sms_returns_false_when_phone_not_verified(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => true,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => null,
        ]);
        $this->assertFalse($user->wantsSmsNotifications());
    }

    public function test_wants_sms_returns_false_without_phone_number(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => true,
            'phone_number'    => null,
            'sms_verified_at' => null,
        ]);
        $this->assertFalse($user->wantsSmsNotifications());
    }

    public function test_has_verified_phone_returns_true_when_number_and_timestamp_set(): void
    {
        $user = User::factory()->create([
            'phone_number'    => '+33612345678',
            'sms_verified_at' => now(),
        ]);
        $this->assertTrue($user->hasVerifiedPhoneNumber());
    }

    public function test_has_verified_phone_returns_false_when_timestamp_missing(): void
    {
        $user = User::factory()->create([
            'phone_number'    => '+33612345678',
            'sms_verified_at' => null,
        ]);
        $this->assertFalse($user->hasVerifiedPhoneNumber());
    }

    // ─── Canaux renvoyés par via() ────────────────────────────────────

    public function test_via_returns_mail_when_email_enabled(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => true,
            'notify_by_sms'   => false,
        ]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertContains('mail', $channels);
        $this->assertNotContains(SmsChannel::class, $channels);
    }

    public function test_via_excludes_mail_when_email_disabled(): void
    {
        $user = User::factory()->create(['notify_by_email' => false, 'notify_by_sms' => false]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertNotContains('mail', $channels);
    }

    public function test_via_returns_sms_channel_when_sms_enabled_and_verified(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => false,
            'notify_by_sms'   => true,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => now(),
        ]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertContains(SmsChannel::class, $channels);
    }

    public function test_via_excludes_sms_channel_when_sms_disabled(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => false,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => now(),
        ]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertNotContains(SmsChannel::class, $channels);
    }

    public function test_via_excludes_sms_channel_when_phone_not_verified(): void
    {
        $user = User::factory()->create([
            'notify_by_sms'   => true,
            'phone_number'    => '+33612345678',
            'sms_verified_at' => null,
        ]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertNotContains(SmsChannel::class, $channels);
    }

    public function test_via_returns_empty_when_all_channels_disabled(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => false,
            'notify_by_sms'   => false,
        ]);
        $channels = (new AccountEventNotification('Sujet', 'Corps'))->via($user);

        $this->assertEmpty($channels);
    }

    // ─── Envoi de notification (Notification::fake) ───────────────────

    public function test_notification_is_sent_when_email_enabled(): void
    {
        Notification::fake();

        $user = User::factory()->create(['notify_by_email' => true]);
        $user->notify(new AccountEventNotification('Sujet', 'Corps'));

        Notification::assertSentTo($user, AccountEventNotification::class);
    }

    // ─── Endpoints HTTP ───────────────────────────────────────────────

    public function test_preferences_page_requires_authentication(): void
    {
        $this->get(route('notifications.preferences.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_preferences_page_is_accessible_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notifications.preferences.edit'))
            ->assertOk();
    }

    public function test_preferences_can_be_updated(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => true,
            'notify_by_sms'   => false,
            'phone_number'    => null,
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.preferences.update'), [
                'notify_by_email' => '0',
                'notify_by_sms'   => '1',
                'phone_number'    => '+33699887766',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->notify_by_email);
        $this->assertTrue($user->notify_by_sms);
        $this->assertSame('+33699887766', $user->phone_number);
    }

    public function test_phone_number_max_length_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notifications.preferences.update'), [
                'phone_number' => str_repeat('1', 31),
            ])
            ->assertSessionHasErrors('phone_number');
    }

    public function test_sms_not_enabled_by_default(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($user->notify_by_sms);
    }

    public function test_email_enabled_by_default(): void
    {
        $user = User::factory()->create();
        $this->assertTrue($user->notify_by_email);
    }
}
