<?php

namespace App\Notifications;

use App\Channels\PushChannel;
use App\Channels\SmsChannel;
use App\Models\EmailTemplate;
use App\Models\Round;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RoundResultNotification extends Notification
{
    use Queueable;

    public function __construct(public Round $round, public User $winner) {}

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
        return 'round_result';
    }

    public function toSms(object $notifiable): string
    {
        $tontine  = $this->round->tontine;
        $isWinner = $notifiable->id === $this->winner->id;
        if ($isWinner) {
            return "🏆 Vous avez gagné le Tour #{$this->round->round_number} de {$tontine->name} ! Cagnotte " .
                   number_format($this->round->pot_amount, 0, ',', ' ') . "€";
        }
        return "📊 {$tontine->name} · Tour #{$this->round->round_number} : {$this->winner->full_name} gagne " .
               number_format($this->round->pot_amount, 0, ',', ' ') . "€";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tontine  = $this->round->tontine;
        $isWinner = $notifiable->id === $this->winner->id;
        $locale   = app()->getLocale();

        $method = $this->round->drawn_by_lot
            ? ($locale === 'en' ? 'draw' : 'tirage au sort')
            : ($locale === 'en' ? 'highest bid' : 'plus haute enchère');

        $paymentBlock = $tontine->paymentBlock();
        $payoutDate   = $this->round->payout_date?->format('d/m/Y');

        $templateKey = $isWinner ? 'round_result_winner' : 'round_result';
        $vars = [
            'recipient_name' => $notifiable->full_name,
            'tontine_name'   => $tontine->name,
            'round_number'   => $this->round->round_number,
            'pot_amount'     => number_format($this->round->pot_amount, 2),
            'method'         => $method,
            'winner_name'    => $this->winner->full_name,
            'payment_info'   => $paymentBlock,
            'payout_date'    => $payoutDate ?? '—',
        ];

        $tpl = EmailTemplate::getFor($templateKey, $locale)?->replaceVars($vars);

        if ($tpl) {
            return $tpl->buildMailMessage(
                "Bonjour {$notifiable->full_name},",
                $locale === 'fr' ? 'Voir les détails' : 'View details',
                route('participant.history')
            );
        }

        $message = (new MailMessage)
            ->subject("🏆 Résultats Tour #{$this->round->round_number} | {$tontine->name}")
            ->greeting("Bonjour {$notifiable->full_name},");

        if ($isWinner) {
            $message->line("🎉 **Félicitations ! Vous avez remporté le Tour #{$this->round->round_number} !**")
                    ->line("Cagnotte : **" . number_format($this->round->pot_amount, 2) . " €**")
                    ->line("Mode de désignation : {$method}");
            if ($payoutDate) {
                $message->line("**Versement prévu le :** {$payoutDate}");
            }
        } else {
            $message->line("Les résultats du Tour #{$this->round->round_number} de **{$tontine->name}** sont disponibles.")
                    ->line("**Gagnant :** {$this->winner->full_name}")
                    ->line("**Cagnotte :** " . number_format($this->round->pot_amount, 2) . " €")
                    ->line("**Mode :** {$method}")
                    ->line("---")
                    ->line("**Coordonnées pour régler votre cotisation :**")
                    ->line($paymentBlock);
        }

        return $message->action('Voir les détails', route('participant.history'));
    }

    public function toPush(object $notifiable): array
    {
        $tontine  = $this->round->tontine;
        $isWinner = $notifiable->id === $this->winner->id;

        return [
            'title' => $isWinner
                ? "🏆 Vous avez gagné le Tour #{$this->round->round_number} !"
                : "📊 Résultats – Tour #{$this->round->round_number}",
            'body'  => $isWinner
                ? "{$tontine->name} · Cagnotte : " . number_format($this->round->pot_amount, 2) . " €"
                : "{$tontine->name} · Gagnant : {$this->winner->full_name}",
            'url'   => route('participant.history'),
            'tag'   => "round-result-{$this->round->id}",
        ];
    }
}
