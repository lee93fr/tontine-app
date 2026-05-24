<?php

namespace App\Notifications;

use App\Models\EmailTemplate;
use App\Models\Tontine;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tontine $tontine,
        public string $signatureUrl,
        public ?User $inviter = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = app()->getLocale();

        $vars = [
            'recipient_name'      => $notifiable->full_name,
            'tontine_name'        => $this->tontine->name,
            'cotisation_amount'   => number_format($this->tontine->cotisation_amount, 2, ',', ' ') . ' €',
            'preliminary_amount'  => $this->tontine->preliminary_amount
                ? number_format($this->tontine->preliminary_amount, 2, ',', ' ') . ' €'
                : '—',
            'max_participants'    => (string) $this->tontine->max_participants,
            'signature_url'       => $this->signatureUrl,
            'inviter_name'        => $this->inviter?->full_name ?? '—',
        ];

        $tpl = EmailTemplate::getFor('signature_request', $locale)?->replaceVars($vars);

        if ($tpl) {
            return $tpl->buildMailMessage(
                "Bonjour {$notifiable->full_name},",
                $locale === 'fr' ? 'Signer le règlement' : 'Sign the regulation',
                $this->signatureUrl
            );
        }

        // Fallback intégré si le template n'a pas été personnalisé
        return (new MailMessage)
            ->subject("✍️ Signature du règlement — {$this->tontine->name}")
            ->greeting("Bonjour {$notifiable->full_name},")
            ->line("Vous êtes invité·e à signer électroniquement le règlement de la tontine **{$this->tontine->name}**.")
            ->line("**Cotisation mensuelle :** " . number_format($this->tontine->cotisation_amount, 2, ',', ' ') . " €")
            ->when($this->tontine->preliminary_amount, fn($m) => $m->line("**Avance initiale :** " . number_format($this->tontine->preliminary_amount, 2, ',', ' ') . " €"))
            ->action('Signer le règlement', $this->signatureUrl)
            ->line("Cette signature constitue votre engagement de participation à la tontine.")
            ->line("Le lien de signature est personnel — ne le partagez pas.");
    }
}
