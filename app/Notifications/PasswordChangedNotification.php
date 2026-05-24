<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();
        $name   = $notifiable->full_name ?: $notifiable->name;

        $vars = ['recipient_name' => $name];

        $tpl = EmailTemplate::getFor('password_changed', $locale)?->replaceVars($vars);
        if ($tpl) {
            return $tpl->buildMailMessage("Bonjour {$name},", null, null);
        }

        return (new MailMessage)
            ->subject('Votre mot de passe a été modifié')
            ->greeting("Bonjour {$name},")
            ->line("Votre mot de passe a été réinitialisé avec succès.")
            ->line("Si vous n'êtes pas à l'origine de cette modification, contactez-nous immédiatement.");
    }
}
