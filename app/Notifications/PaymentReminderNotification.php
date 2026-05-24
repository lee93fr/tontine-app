<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use App\Models\EmailTemplate;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Payment $payment) {}

    public function via(object $notifiable): array
    {
        // Email + push uniquement. Le SMS reste manuel à la main de l'admin
        // pour éviter de consommer le quota Brevo.
        return ['mail', PushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $round    = $this->payment->round;
        $tontine  = $round->tontine;
        $locale   = app()->getLocale();
        $dueDate  = optional($this->payment->due_date)->format('d/m/Y') ?? '—';
        $daysLate = $this->payment->daysLate();

        $vars = [
            'recipient_name' => $notifiable->full_name,
            'tontine_name'   => $tontine->name,
            'round_number'   => (string) $round->round_number,
            'amount'         => number_format($this->payment->amount, 2, ',', ' ') . ' €',
            'due_date'       => $dueDate,
            'days_late'      => (string) $daysLate,
            'payment_info'   => $tontine->paymentBlock(),
        ];

        $tpl = EmailTemplate::getFor('payment_reminder', $locale)?->replaceVars($vars);

        if ($tpl) {
            return $tpl->buildMailMessage(
                "Bonjour {$notifiable->full_name},",
                $locale === 'fr' ? 'Voir le détail' : 'View details',
                route('participant.tontine', $tontine)
            );
        }

        // Fallback intégré si le template n'a pas été personnalisé
        $msg = (new MailMessage)
            ->subject("🔔 Rappel paiement — Tour #{$round->round_number} | {$tontine->name}")
            ->greeting("Bonjour {$notifiable->full_name},")
            ->line("Nous n'avons pas encore enregistré votre paiement pour le **Tour #{$round->round_number}** de la tontine **{$tontine->name}**.")
            ->line("**Montant dû :** " . number_format($this->payment->amount, 2, ',', ' ') . " €")
            ->line("**Date d'échéance :** {$dueDate}");

        if ($daysLate > 0) {
            $msg->line("**Retard :** {$daysLate} jour(s)");
        }

        return $msg
            ->action('Voir mes paiements', route('participant.tontine', $tontine))
            ->line("---")
            ->line("**Coordonnées pour régler votre cotisation :**")
            ->line($tontine->paymentBlock());
    }

    public function toPush(object $notifiable): array
    {
        $round   = $this->payment->round;
        $tontine = $round->tontine;
        $daysLate = $this->payment->daysLate();

        return [
            'title' => "🔔 Rappel paiement – Tour #{$round->round_number}",
            'body'  => $daysLate > 0
                ? "{$tontine->name} · " . number_format($this->payment->amount, 2, ',', ' ') . " € · {$daysLate}j de retard"
                : "{$tontine->name} · " . number_format($this->payment->amount, 2, ',', ' ') . " € à régler",
            'url'   => route('participant.tontine', $tontine),
            'tag'   => "payment-reminder-{$this->payment->id}",
        ];
    }

    /** Pour le canal SMS manuel (envoi déclenché séparément par l'admin) */
    public function toSms(object $notifiable): string
    {
        $round   = $this->payment->round;
        $tontine = $round->tontine;
        $amount  = number_format($this->payment->amount, 0, ',', ' ');
        return "🔔 Rappel : votre cotisation de {$amount}€ pour le Tour #{$round->round_number} de {$tontine->name} est en attente.";
    }
}
