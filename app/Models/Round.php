<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Round extends Model
{
    use HasFactory;

    protected $fillable = [
        'tontine_id', 'type', 'round_number', 'pot_amount', 'preliminary_amount',
        'bid_opens_at', 'bid_closes_at', 'payment_due_at', 'payout_date', 'status',
        'winner_id', 'winning_bid', 'drawn_by_lot', 'notes',
    ];

    protected $casts = [
        'bid_opens_at'    => 'datetime',
        'bid_closes_at'   => 'datetime',
        'payment_due_at'  => 'date',
        'payout_date'     => 'date',
        'pot_amount'      => 'decimal:2',
        'preliminary_amount' => 'decimal:2',
        'winning_bid'     => 'decimal:2',
        'drawn_by_lot'    => 'boolean',
    ];

    public function isPreliminary(): bool
    {
        return $this->type === 'preliminary';
    }

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class)->orderByDesc('amount')->orderBy('bid_at');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'drawn', 'paid']);
    }

    public function getSecondsRemainingAttribute(): int
    {
        if ($this->isClosed()) return 0;
        if (!$this->bid_closes_at) return 0;
        return max(0, now()->diffInSeconds($this->bid_closes_at, false));
    }

    /**
     * Determine the winner with tontine rules:
     * - Highest bid wins (capped at bid_cap)
     * - If tie at cap → random draw among the tied top bidders
     * - If nobody bids above 0% → random draw among participants who registered
     *   at 0% (a 0% bid is a voluntary opt-in to the draw, not a competitive offer)
     * - If literally nobody has bid at all → random draw among all eligible participants
     */
    public function resolveWinner(): ?User
    {
        $cap = $this->tontine->bid_cap;

        // Participants encore éligibles (n'ont pas épuisé leurs tours)
        $allParticipants = $this->tontine->participants()->get();
        $eligibleIds = $allParticipants
            ->filter(fn($p) => $p->pivot->wins_count < $p->pivot->slots)
            ->pluck('id');

        $bids = $this->bids()->get()->filter(fn($b) => $eligibleIds->contains($b->user_id));

        if ($bids->isEmpty()) {
            // Tirage au sort parmi les participants éligibles (fallback : tous)
            $eligible = $allParticipants->filter(fn($p) => $eligibleIds->contains($p->id));
            if ($eligible->isEmpty()) {
                $eligible = $allParticipants;
            }

            $winner = $eligible->random();
            $this->update([
                'winner_id'    => $winner->id,
                'winning_bid'  => 0,
                'drawn_by_lot' => true,
                'status'       => 'drawn',
            ]);
            $this->tontine->participants()->updateExistingPivot($winner->id, [
                'wins_count' => \Illuminate\Support\Facades\DB::raw('wins_count + 1'),
            ]);
            return $winner;
        }

        $maxBid = $bids->max('amount');
        $topBidders = $bids->where('amount', $maxBid);

        // Si plusieurs au plafond ou un seul gagnant
        if ($topBidders->count() > 1 || $maxBid >= $cap) {
            $atCap = $bids->where('amount', '>=', $cap);
            $pool = $atCap->isNotEmpty() ? $atCap : $topBidders;
            $winnerBid = $pool->random();
            $drawn = true;
        } else {
            $winnerBid = $topBidders->first();
            $drawn = false;
        }

        $this->update([
            'winner_id'    => $winnerBid->user_id,
            'winning_bid'  => $winnerBid->amount,
            'drawn_by_lot' => $drawn,
            'status'       => 'drawn',
        ]);

        $this->tontine->participants()->updateExistingPivot($winnerBid->user_id, [
            'wins_count' => \Illuminate\Support\Facades\DB::raw('wins_count + 1'),
        ]);

        return $winnerBid->user;
    }
}
