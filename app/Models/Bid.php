<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Bid extends Model
{
    use HasFactory;

    protected $fillable = ['round_id', 'user_id', 'amount', 'bid_at'];

    protected $casts = [
        'amount' => 'decimal:2',
        'bid_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
