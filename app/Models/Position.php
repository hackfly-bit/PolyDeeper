<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    protected $fillable = [
        'market_ref_id',
        'market_id',
        'condition_id',
        'token_id',
        'order_id',
        'side',
        'entry_price',
        'size',
        'status',
        'closed_at',
        'exit_reason',
    ];

    protected $casts = [
        'entry_price' => 'float',
        'size' => 'float',
        'closed_at' => 'datetime',
    ];

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class, 'market_ref_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
