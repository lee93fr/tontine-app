<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantBalanceVersion extends Model
{
    protected $fillable = [
        'tontine_id',
        'user_id',
        'version',
        'previous_balance',
        'new_balance',
        'reason',
        'changed_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'previous_balance' => 'decimal:2',
        'new_balance' => 'decimal:2',
    ];

    public function tontine()
    {
        return $this->belongsTo(Tontine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
