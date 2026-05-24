<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\Tontine;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function sendPushNotification(int $userId, array $payload): array
    {
        if (! $this->isConfigured()) {
            Log::warning('Push non envoye : configuration VAPID manquante.', ['user_id' => $userId]);

            return [
                'configured' => false,
                'sent' => 0,
                'failed' => 0,
                'disabled' => 0,
            ];
        }

        $subscriptions = PushSubscription::query()
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->get();

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    public function sendPushNotificationToTontine(int $tontineId, array $payload): array
    {
        $tontine = Tontine::query()->find($tontineId);

        if (! $tontine) {
            return [
                'configured' => $this->isConfigured(),
                'sent' => 0,
                'failed' => 0,
                'disabled' => 0,
            ];
        }

        $userIds = $tontine->participants()->pluck('users.id');

        if (! $this->isConfigured()) {
            Log::warning('Push tontine non envoye : configuration VAPID manquante.', ['tontine_id' => $tontineId]);

            return [
                'configured' => false,
                'sent' => 0,
                'failed' => 0,
                'disabled' => 0,
            ];
        }

        $subscriptions = PushSubscription::query()
            ->whereIn('user_id', $userIds)
            ->where('enabled', true)
            ->get();

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.push.vapid_public_key'))
            && filled(config('services.push.vapid_private_key'))
            && filled(config('services.push.vapid_subject'));
    }

    private function sendToSubscriptions($subscriptions, array $payload): array
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.push.vapid_subject'),
                'publicKey' => config('services.push.vapid_public_key'),
                'privateKey' => config('services.push.vapid_private_key'),
            ],
        ]);

        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
        $sent = 0;
        $failed = 0;
        $disabled = 0;

        foreach ($subscriptions as $pushSubscription) {
            $subscription = Subscription::create([
                'endpoint' => $pushSubscription->endpoint,
                'publicKey' => $pushSubscription->p256dh,
                'authToken' => $pushSubscription->auth,
                'contentEncoding' => 'aes128gcm',
            ]);

            try {
                $report = $webPush->sendOneNotification($subscription, $encodedPayload);
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Echec envoi push.', [
                    'subscription_id' => $pushSubscription->id,
                    'user_id' => $pushSubscription->user_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($report->isSuccess()) {
                $sent++;
                $pushSubscription->forceFill(['last_used_at' => now()])->save();

                continue;
            }

            $failed++;

            if ($report->isSubscriptionExpired() || in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                $disabled++;
                $pushSubscription->forceFill(['enabled' => false])->save();
            }

            Log::warning('Notification push refusee par le endpoint.', [
                'subscription_id' => $pushSubscription->id,
                'user_id' => $pushSubscription->user_id,
                'reason' => $report->getReason(),
            ]);
        }

        return [
            'configured' => true,
            'sent' => $sent,
            'failed' => $failed,
            'disabled' => $disabled,
        ];
    }
}
