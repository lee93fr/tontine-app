<?php

namespace App\Channels;

use App\Services\SmsNotificationService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsNotificationService $sms) {}

    /**
     * Envoie la notification SMS si :
     *  - la notification implémente toSms() ;
     *  - l'utilisateur a un numéro vérifié et veut recevoir ce type d'événement ;
     *  - la notification expose un identifiant d'événement via smsEvent() (sinon on envoie).
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $phone = $notifiable->phone_number ?? $notifiable->phone ?? null;
        if (! $phone) {
            return;
        }

        // Vérifie la préférence par événement si la notification le déclare
        if (method_exists($notification, 'smsEvent') && method_exists($notifiable, 'wantsSmsFor')) {
            $event = $notification->smsEvent();
            if ($event && ! $notifiable->wantsSmsFor($event)) {
                return;
            }
        }

        $message = $notification->toSms($notifiable);
        $this->sms->send($phone, $message);
    }
}
