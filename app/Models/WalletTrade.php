<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTrade extends Model
{
    protected $fillable = [
        'wallet_id',
        'market_id',
        'side',
        'price',
        'size',
        'traded_at',
    ];

    protected $casts = [
        'traded_at' => 'datetime',
        'price' => 'float',
        'size' => 'float',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}