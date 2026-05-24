<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class InvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tontine = $this->invitation->tontine;
        $inviter = $this->invitation->inviter;
        $expires = $this->invitation->expires_at->format('d/m/Y');
        $locale  = app()->getLocale();
        $url     = route('invitation.accept', $this->invitation->token);

        // Invitation standalone (sans tontine)
        if (!$tontine) {
            return (new MailMessage)
                ->subject('Invitation à créer votre compte')
                ->greeting('Bonjour,')
                ->line("{$inviter->name} vous invite à rejoindre la plateforme de tontine.")
                ->line("Cette invitation expire le **{$expires}**.")
                ->action('Créer mon compte', $url)
                ->line("Si vous ne souhaitez pas créer de compte, ignorez cet email.");
        }

        $tpl = EmailTemplate::getFor('invitation', $locale)?->replaceVars([
            'tontine_name'      => $tontine->name,
            'inviter_name'      => $inviter->name,
            'cotisation_amount' => number_format($tontine->cotisation_amount, 2),
            'max_participants'  => $tontine->max_participants,
            'expires_at'        => $expires,
        ]);

        if ($tpl) {
            return $tpl->buildMailMessage(
                'Bonjour,',
                $locale === 'fr' ? "Accepter l'invitation" : 'Accept invitation',
                $url
            );
        }

        return (new MailMessage)
            ->subject("Invitation à rejoindre la tontine : {$tontine->name}")
            ->greeting('Bonjour,')
            ->line("{$inviter->name} vous invite à rejoindre la tontine **{$tontine->name}**.")
            ->line('**Cotisation mensuelle :** ' . number_format($tontine->cotisation_amount, 2) . ' €')
            ->line("**Participants max :** {$tontine->max_participants}")
            ->line("Cette invitation expire le **{$expires}**.")
            ->action("Accepter l'invitation", $url)
            ->line("Si vous ne souhaitez pas rejoindre cette tontine, ignorez cet email.");
    }
}
