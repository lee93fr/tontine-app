<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use App\Channels\SmsChannel;
use App\Models\EmailTemplate;
use App\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RoundOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(public Round $round) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail', PushChannel::class];
        if (method_exists($notifiable, 'wantsSmsFor') && $notifiable->wantsSmsFor($this->smsEvent())) {
            $channels[] = SmsChannel::class;
        }
        return $channels;
    }

    public function smsEvent(): string
    {
        return 'round_opened';
    }

    public function toSms(object $notifiable): string
    {
        $tontine = $this->round->tontine;
        $closes  = $this->round->bid_closes_at->format('d/m H:i');
        return "🎯 {$tontine->name} · Tour #{$this->round->round_number} ouvert · Cagnotte " .
               number_format($this->round->pot_amount, 0, ',', ' ') . "€ · Clôture {$closes}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tontine = $this->round->tontine;
        $closes  = $this->round->bid_closes_at->format('d/m/Y à H:i');
        $locale  = app()->getLocale();

        $paymentBlock = $tontine->paymentBlock();
        $payoutDate   = $this->round->payout_date?->format('d/m/Y');

        $tpl = EmailTemplate::getFor('round_opened', $locale)?->replaceVars([
            'recipient_name' => $notifiable->full_name,
            'tontine_name'   => $tontine->name,
            'round_number'   => $this->round->round_number,
            'pot_amount'     => number_format($this->round->pot_amount, 2),
            'closes_at'      => $closes,
            'cap'            => $tontine->bid_cap,
            'payment_info'   => $paymentBlock,
            'payout_date'    => $payoutDate ?? '—',
        ]);

        if ($tpl) {
            return $tpl->buildMailMessage(
                "Bonjour {$notifiable->full_name},",
                $locale === 'fr' ? 'Enchérir maintenant' : 'Bid now',
                route('participant.tontine', $tontine)
            );
        }

        $msg = (new MailMessage)
            ->subject("🎯 Enchères ouvertes - Tour #{$this->round->round_number} | {$tontine->name}")
            ->greeting("Bonjour {$notifiable->full_name},")
            ->line("Les enchères pour le **Tour #{$this->round->round_number}** de la tontine **{$tontine->name}** sont maintenant ouvertes.")
            ->line("**Cagnotte du tour :** " . number_format($this->round->pot_amount, 2) . " €")
            ->line("**Clôture des enchères :** {$closes}")
            ->line("**Plafond d'enchère :** {$tontine->bid_cap}%");

        if ($payoutDate) {
            $msg->line("**Versement prévu :** {$payoutDate}");
        }

        $msg->action('Enchérir maintenant', route('participant.tontine', $tontine))
            ->line("---")
            ->line("**Comment régler votre cotisation :**")
            ->line($paymentBlock);

        return $msg;
    }

    public function toPush(object $notifiable): array
    {
        $tontine = $this->round->tontine;

        return [
            'title' => "🎯 Enchères ouvertes – Tour #{$this->round->round_number}",
            'body'  => "{$tontine->name} · Cagnotte : " . number_format($this->round->pot_amount, 2) . " € · Clôture : " . $this->round->bid_closes_at->format('d/m H:i'),
            'url'   => route('participant.tontine', $tontine),
            'tag'   => "round-opened-{$this->round->id}",
        ];
    }
}
