<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AccountEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $subject,
        private string $body,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable->wantsEmailNotifications()) {
            $channels[] = 'mail';
        }

        if ($notifiable->wantsSmsNotifications()) {
            $channels[] = SmsChannel::class;
        }

        $channels[] = PushChannel::class;

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $greeting = 'Bonjour' . ($notifiable->full_name ? ' ' . $notifiable->full_name : '') . ',';

        return (new MailMessage)
            ->subject($this->subject)
            ->greeting($greeting)
            ->line($this->body);
    }

    public function toSms(object $notifiable): string
    {
        return $this->body;
    }

    public function toPush(object $notifiable): array
    {
        return [
            'title' => $this->subject,
            'body'  => $this->body,
            'url'   => url('/'),
            'tag'   => 'account-event',
        ];
    }
}
