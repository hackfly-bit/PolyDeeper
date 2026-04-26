<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signal extends Model
{
    protected $fillable = [
        'market_id',
        'market_ref_id',
        'condition_id',
        'token_id',
        'direction',
        'strength',
        'wallet_id',
    ];

    protected $casts = [
        'direction' => 'integer',
        'strength' => 'float',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_ref_id');
    }
}
