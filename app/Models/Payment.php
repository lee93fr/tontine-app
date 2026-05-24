<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'round_id', 'user_id', 'amount', 'paid_amount', 'due_date', 'status',
        'paid_at', 'reference', 'notes',
        'last_reminder_sent_at', 'reminder_count', 'last_sms_reminder_sent_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'last_sms_reminder_sent_at' => 'datetime',
    ];

    /** Reste à payer (≥ 0) */
    public function remainingAmount(): float
    {
        return max(0, (float) $this->amount - (float) $this->paid_amount);
    }

    public function isFullyPaid(): bool
    {
        return $this->remainingAmount() < 0.01;
    }

    public function isPartiallyPaid(): bool
    {
        return (float) $this->paid_amount > 0 && ! $this->isFullyPaid();
    }

    /**
     * Détermine le statut final en fonction de paid_amount.
     * - paid_amount >= amount → 'paid'
     * - paid_amount > 0 et < amount → 'partial'
     * - sinon : on garde 'late' si déjà en retard, sinon 'pending'
     */
    public function deriveStatus(): string
    {
        // Un montant de 0€ est considéré comme payé (cas du gagnant du tour)
        if ((float) $this->amount <= 0 || $this->isFullyPaid()) {
            return 'paid';
        }
        if ($this->isPartiallyPaid()) {
            return 'partial';
        }
        // Pas payé du tout
        if ($this->due_date && $this->due_date->isPast()) {
            return 'late';
        }
        return 'pending';
    }

    public function daysLate(): int
    {
        if (!$this->due_date) {
            return 0;
        }
        $reference = $this->paid_at ?? now();
        $days = (int) $this->due_date->diffInDays($reference, false);
        return max(0, $days);
    }

    public function penaltyAmount(float $perDay, ?float $cap): float
    {
        $penalty = $this->daysLate() * $perDay;
        if ($cap !== null) {
            $penalty = min($penalty, $cap);
        }
        return $penalty;
    }

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
