<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = ['tontine_id', 'invited_by', 'email', 'phone', 'channel', 'token', 'expires_at', 'accepted_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isValid(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at->isFuture();
    }

    public static function generate(?int $tontineId, int $inviterId, ?string $email, ?string $phone = null, string $channel = 'email'): self
    {
        return self::create([
            'tontine_id' => $tontineId,
            'invited_by' => $inviterId,
            'email'      => $email,
            'phone'      => $phone,
            'channel'    => $channel,
            'token'      => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /** Destinataire affichable (email ou téléphone selon le canal) */
    public function getRecipientAttribute(): string
    {
        return $this->channel === 'sms'
            ? ($this->phone ?? '')
            : ($this->email ?? '');
    }
}
