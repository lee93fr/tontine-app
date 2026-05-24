<?php

namespace App\Channels;

use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Notifications\Notification;

class PushChannel
{
    public function __construct(private PushNotificationService $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (!($notifiable instanceof User)) {
            return;
        }

        if (!method_exists($notification, 'toPush')) {
            return;
        }

        $payload = $notification->toPush($notifiable);

        if (empty($payload)) {
            return;
        }

        $this->push->sendPushNotification($notifiable->id, $payload);
    }
}
