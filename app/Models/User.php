<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'first_name', 'email', 'password', 'role', 'active', 'phone',
        'address', 'postal_code', 'city', 'iban', 'bic', 'managed_tontine_id', 'partner_id',
        'delegate_id', 'notify_by_email', 'notify_by_sms', 'phone_number', 'sms_verified_at',
        'sms_notification_preferences',
    ];

    public const SMS_EVENTS = [
        'round_opened'  => 'Ouverture d\'un tour (enchères ouvertes)',
        'round_result'  => 'Résultat de tour (gagnant désigné)',
        'bid_placed'    => 'Enchère placée (confirmation pour vous-même)',
        'invitation'    => 'Invitation à rejoindre une tontine',
        'account_event' => 'Événements compte (création, désactivation…)',
    ];

    public const SMS_EVENTS_DEFAULT = ['round_opened', 'round_result'];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'notify_by_email' => 'boolean',
            'notify_by_sms' => 'boolean',
            'sms_verified_at' => 'datetime',
            'sms_notification_preferences' => 'array',
        ];
    }

    /**
     * Cet utilisateur veut-il un SMS pour l'événement donné ?
     * Combine la préférence globale + la préférence par événement.
     */
    public function wantsSmsFor(string $event): bool
    {
        if (! $this->wantsSmsNotifications()) {
            return false;
        }
        $prefs = $this->sms_notification_preferences ?? self::SMS_EVENTS_DEFAULT;
        return in_array($event, $prefs, true);
    }

    public function wantsEmailNotifications(): bool
    {
        return (bool) $this->notify_by_email;
    }

    public function wantsSmsNotifications(): bool
    {
        return (bool) $this->notify_by_sms && $this->hasVerifiedPhoneNumber();
    }

    public function hasVerifiedPhoneNumber(): bool
    {
        return $this->phone_number !== null && $this->sms_verified_at !== null;
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ? $this->first_name.' ' : '').$this->name);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin']);
    }

    public function isAdminLocal(): bool
    {
        return $this->role === 'admin_local';
    }

    public function isParticipant(): bool
    {
        return $this->role === 'participant';
    }

    public function isTresorier(): bool
    {
        return $this->role === 'tresorier';
    }

    public function managedTontine()
    {
        return $this->belongsTo(Tontine::class, 'managed_tontine_id');
    }

    /** Binôme : le partenaire principal (FK sur l'utilisateur secondaire). */
    public function partner()
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /** Binôme : l'utilisateur secondaire lié à ce principal. */
    public function partnerOf()
    {
        return $this->hasOne(User::class, 'partner_id');
    }

    /** Délégation : l'utilisateur autorisé à enchérir à ma place. */
    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /** Délégation : les utilisateurs pour qui j'enchéris. */
    public function delegatedFor()
    {
        return $this->hasMany(User::class, 'delegate_id');
    }

    /**
     * Retourne l'utilisateur principal du slot (self si principal, partner si secondaire).
     * Toutes les données de tontine (bids, paiements) sont liées au principal.
     */
    public function primaryUser(): self
    {
        return $this->partner_id ? $this->partner : $this;
    }

    public function managedTontines()
    {
        return $this->belongsToMany(Tontine::class, 'tresorier_tontines')->withTimestamps();
    }

    /** Tontines gérées ou attribuées à cet admin_local */
    public function adminLocalTontines()
    {
        return $this->belongsToMany(Tontine::class, 'admin_local_tontines')
                    ->withPivot('can_edit')
                    ->withTimestamps();
    }

    /** Tontines que cet admin_local peut modifier (créées par lui) */
    public function ownedTontines()
    {
        return $this->belongsToMany(Tontine::class, 'admin_local_tontines')
                    ->withPivot('can_edit')
                    ->withTimestamps()
                    ->wherePivot('can_edit', true);
    }

    public function tontines()
    {
        return $this->belongsToMany(Tontine::class, 'tontine_user')
            ->withPivot([
                'slots', 'wins_count', 'balance',
                'signature_submission_id', 'signature_status',
                'signature_sent_at', 'signed_at', 'signed_pdf_url', 'signature_signer_url',
                'signature_error',
                'partner_signature_submission_id', 'partner_signature_status',
                'partner_signature_sent_at', 'partner_signed_at', 'partner_signed_pdf_url',
                'partner_signature_signer_url', 'partner_signature_error',
            ])
            ->withTimestamps();
    }

    /** Tontines marquées en favori par cet utilisateur (admin/superadmin) */
    public function favoriteTontines()
    {
        return $this->belongsToMany(Tontine::class, 'user_tontine_favorites')->withTimestamps();
    }

    /** Tontines retenues comme filtre du dashboard admin (préférence persistée). */
    public function dashboardTontines()
    {
        return $this->belongsToMany(Tontine::class, 'user_dashboard_tontines')->withTimestamps();
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function wonRounds()
    {
        return $this->hasMany(Round::class, 'winner_id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
