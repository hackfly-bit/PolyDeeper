<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'name',
        'address',
        'weight',
        'pnl',
        'win_rate',
        'roi',
        'last_active',
    ];

    protected $casts = [
        'last_active' => 'datetime',
        'weight' => 'float',
        'pnl' => 'float',
        'win_rate' => 'float',
        'roi' => 'float',
    ];

    public function trades(): HasMany
    {
        return $this->hasMany(WalletTrade::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
