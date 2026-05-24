<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use App\Models\Bid;
use App\Models\EmailTemplate;
use App\Models\Round;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class BidPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(public Round $round, public Bid $bid) {}

    public function via(object $notifiable): array
    {
        return ['mail', PushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tontine = $this->round->tontine;
        $amount  = (int) $this->bid->amount;
        $highest = (int) $this->round->bids()->max('amount');
        $locale  = app()->getLocale();

        $tpl = EmailTemplate::getFor('bid_placed', $locale)?->replaceVars([
            'recipient_name' => $notifiable->name,
            'tontine_name'   => $tontine->name,
            'round_number'   => $this->round->round_number,
            'amount'         => $amount,
            'highest'        => $highest,
            'cap'            => $tontine->bid_cap,
            'closes_at'      => $this->round->bid_closes_at->format('d/m/Y à H:i'),
        ]);

        if ($tpl) {
            return $tpl->buildMailMessage(
                "Bonjour {$notifiable->name},",
                $locale === 'fr' ? 'Voir et enchérir' : 'View and bid',
                route('participant.tontine', $tontine)
            );
        }

        return (new MailMessage)
            ->subject("🎯 Nouvelle enchère - Tour #{$this->round->round_number} | {$tontine->name}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une nouvelle enchère vient d'être placée pour le **Tour #{$this->round->round_number}** de la tontine **{$tontine->name}**.")
            ->line("**Montant de l'enchère :** {$amount}%")
            ->line("**Enchère la plus haute actuellement :** {$highest}%")
            ->line("**Plafond :** {$tontine->bid_cap}%")
            ->line("Les enchères se clôturent le {$this->round->bid_closes_at->format('d/m/Y à H:i')}.")
            ->action('Voir et enchérir', route('participant.tontine', $tontine));
    }

    public function toPush(object $notifiable): array
    {
        $tontine = $this->round->tontine;
        $amount  = (int) $this->bid->amount;
        $highest = (int) $this->round->bids()->max('amount');

        return [
            'title' => "🎯 Nouvelle enchère – Tour #{$this->round->round_number}",
            'body'  => "{$tontine->name} · {$amount}% placé · Max actuel : {$highest}%",
            'url'   => route('participant.tontine', $tontine),
            'tag'   => "bid-round-{$this->round->id}",
        ];
    }
}
