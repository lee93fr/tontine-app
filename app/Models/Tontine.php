<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Tontine extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'description', 'cotisation_amount', 'max_participants', 'bid_cap', 'status',
        'penalty_per_day', 'penalty_cap',
        'has_preliminary', 'preliminary_amount', 'preliminary_period_start', 'preliminary_period_end',
        'has_bidding', 'bid_requires_signature', 'first_round_month', 'bid_day_open', 'bid_day_close', 'payment_day', 'payout_day', 'rounds_count',
        'participants_locked', 'payment_info',
        'docuseal_template_id',
        'docuseal_template_individual_id',
        'docuseal_template_binome_id',
        'docuseal_template_double_id',
    ];

    protected $casts = [
        'cotisation_amount'        => 'decimal:2',
        'bid_cap'                  => 'integer',
        'max_participants'         => 'integer',
        'penalty_per_day'          => 'decimal:2',
        'penalty_cap'              => 'decimal:2',
        'has_preliminary'          => 'boolean',
        'preliminary_amount'       => 'decimal:2',
        'preliminary_period_start' => 'date',
        'preliminary_period_end'   => 'date',
        'has_bidding'              => 'boolean',
        'bid_requires_signature'   => 'boolean',
        'first_round_month'        => 'date',
        'bid_day_open'             => 'integer',
        'bid_day_close'            => 'integer',
        'payment_day'              => 'integer',
        'payout_day'               => 'integer',
        'rounds_count'             => 'integer',
        'participants_locked'      => 'boolean',
        'payment_info'             => 'array',
        'docuseal_template_id'             => 'integer',
        'docuseal_template_individual_id'  => 'integer',
        'docuseal_template_binome_id'      => 'integer',
        'docuseal_template_double_id'      => 'integer',
    ];

    public function participants()
    {
        return $this->belongsToMany(User::class, 'tontine_user')
                    ->withPivot([
                        'slots', 'wins_count',
                        'signature_submission_id', 'signature_status',
                        'signature_sent_at', 'signed_at', 'signed_pdf_url', 'signature_signer_url',
                        'signature_error',
                        'partner_signature_submission_id', 'partner_signature_status',
                        'partner_signature_sent_at', 'partner_signed_at', 'partner_signed_pdf_url',
                        'partner_signature_signer_url', 'partner_signature_error',
                    ])
                    ->withTimestamps();
    }

    public function tresoriers()
    {
        return $this->belongsToMany(User::class, 'tresorier_tontines')->withTimestamps();
    }

    public function rounds()
    {
        return $this->hasMany(Round::class)->orderBy('round_number');
    }

    public function currentRound()
    {
        return $this->hasOne(Round::class)->whereIn('status', ['open', 'pending'])->orderBy('round_number');
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    public function getParticipantsCountAttribute(): int
    {
        return $this->participants()->count();
    }

    public function getPotAmountAttribute(): float
    {
        $participants        = $this->participants()->get();
        $totalSlots          = $participants->sum(fn($p) => $p->pivot->slots);
        $standardRoundsCount = max(1, $this->rounds()->where('type', 'standard')->count());

        $preliminaryPerRound = 0;
        if ($this->has_preliminary && $this->preliminary_amount) {
            $preliminaryPerRound = ($totalSlots * (float) $this->preliminary_amount) / $standardRoundsCount;
        }

        if ($this->has_bidding) {
            $avgBidPct = (float) ($this->rounds()
                ->where('type', 'standard')
                ->whereNotNull('winner_id')
                ->where('winning_bid', '>', 0)
                ->avg('winning_bid') ?? 0.0);

            $pastWonSlots  = $participants->sum(fn($p) => min($p->pivot->wins_count, $p->pivot->slots));
            $eligibleSlots = $participants->sum(fn($p) => max(0, $p->pivot->slots - $p->pivot->wins_count));

            $basePot = $pastWonSlots * (float) $this->cotisation_amount
                + max(0, $eligibleSlots - 1) * (float) $this->cotisation_amount * (1 - $avgBidPct / 100);
        } else {
            $basePot = max(0, $totalSlots - 1) * (float) $this->cotisation_amount;
        }

        return $preliminaryPerRound + $basePot;
    }

    public function paymentBlock(): string
    {
        $info = $this->payment_info ?? [];
        if (empty($info)) {
            return "Contactez l'administrateur pour connaître les modalités de paiement.";
        }

        $lines = [];

        $contact = trim(($info['tresorier_firstname'] ?? '').' '.($info['tresorier_name'] ?? ''));
        if ($contact) {
            $lines[] = "**Trésorier :** {$contact}";
        }
        if (!empty($info['tresorier_phone'])) {
            $lines[] = "**Tél. :** {$info['tresorier_phone']}";
        }
        if (!empty($info['iban'])) {
            $lines[] = "**Virement bancaire :** IBAN {$info['iban']}".(!empty($info['bic']) ? " — BIC {$info['bic']}" : '');
        }
        if (!empty($info['revolut_link'])) {
            $lines[] = "**Revolut :** {$info['revolut_link']}";
        }
        if (!empty($info['address'])) {
            $lines[] = "**Espèces :** {$info['address']}";
        }

        return implode("\n", $lines) ?: "Contactez l'administrateur pour connaître les modalités de paiement.";
    }

    public function recalcPotAmounts(): void
    {
        $participants        = $this->participants()->get();
        $totalSlots          = $participants->sum(fn($p) => $p->pivot->slots);
        $standardRoundsCount = $this->rounds()->where('type', 'standard')->count();

        $preliminaryPerRound = 0;
        if ($this->has_preliminary && $this->preliminary_amount && $standardRoundsCount > 0) {
            $preliminaryTotal    = $totalSlots * (float) $this->preliminary_amount;
            $preliminaryPerRound = $preliminaryTotal / $standardRoundsCount;
        }

        if ($this->has_bidding) {
            // Enchère moyenne des tours déjà tirés (hors tirages au sort à 0%)
            $avgBidPct = (float) ($this->rounds()
                ->where('type', 'standard')
                ->whereNotNull('winner_id')
                ->where('winning_bid', '>', 0)
                ->avg('winning_bid') ?? 0.0);

            // Slots déjà remportés par des gagnants passés (paient 100% sur les tours suivants)
            $pastWonSlots  = $participants->sum(fn($p) => min($p->pivot->wins_count, $p->pivot->slots));
            // Slots encore éligibles (pas encore gagnés)
            $eligibleSlots = $participants->sum(fn($p) => max(0, $p->pivot->slots - $p->pivot->wins_count));

            // Gagnants passés → 100% ; non-gagnants éligibles → (1 − enchère%) ; gagnant du tour → 0 (d'où −1)
            $basePot = round(
                $pastWonSlots * (float) $this->cotisation_amount
                + max(0, $eligibleSlots - 1) * (float) $this->cotisation_amount * (1 - $avgBidPct / 100),
                2
            );
        } else {
            $basePot = round(max(0, $totalSlots - 1) * (float) $this->cotisation_amount, 2);
        }

        // Ne met à jour que les tours sans gagnant afin de préserver les montants exacts des tours tirés
        $this->rounds()->where('type', 'standard')->whereNull('winner_id')->update([
            'pot_amount' => round($preliminaryPerRound + $basePot, 2),
        ]);

        if ($this->has_preliminary && $this->preliminary_amount) {
            $this->rounds()->where('type', 'preliminary')->whereNull('winner_id')->update([
                'pot_amount' => round($totalSlots * (float) $this->preliminary_amount, 2),
            ]);
        }
    }
}
