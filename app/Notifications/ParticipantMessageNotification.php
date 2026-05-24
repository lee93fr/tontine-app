<?php

namespace App\Notifications;

use App\Models\Tontine;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ParticipantMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tontine $tontine,
        public User $sender,
        public string $subject,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[{$this->tontine->name}] Message de {$this->sender->full_name} — {$this->subject}")
            ->greeting("Bonjour {$notifiable->full_name},")
            ->line("Un participant de la tontine **{$this->tontine->name}** vous a envoyé un message via l'espace membre.")
            ->line("**De :** {$this->sender->full_name} ({$this->sender->email})")
            ->line("**Objet :** {$this->subject}")
            ->line("---")
            ->line($this->body)
            ->line("---")
            ->line("Vous pouvez répondre directement à cet email pour contacter le participant.");
    }
}
