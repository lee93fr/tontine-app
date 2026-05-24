<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $expire  = config('auth.passwords.users.expire', 60);
        $locale  = app()->getLocale();
        $name    = $notifiable->full_name ?: $notifiable->name;

        $vars = [
            'recipient_name' => $name,
            'reset_url'      => $url,
            'expire_minutes' => (string) $expire,
        ];

        $tpl = EmailTemplate::getFor('password_reset', $locale)?->replaceVars($vars);
        if ($tpl) {
            return $tpl->buildMailMessage("Bonjour {$name},", 'Réinitialiser mon mot de passe', $url);
        }

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting("Bonjour {$name},")
            ->line("Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.")
            ->action('Réinitialiser mon mot de passe', $url)
            ->line("Ce lien expire dans **{$expire} minutes**.")
            ->line("Si vous n'avez pas demandé de réinitialisation, vous pouvez ignorer cet email.");
    }
}
