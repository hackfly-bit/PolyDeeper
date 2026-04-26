<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'position_id',
        'market_id',
        'polymarket_account_id',
        'condition_id',
        'token_id',
        'side',
        'order_type',
        'price',
        'size',
        'filled_size',
        'status',
        'polymarket_order_id',
        'client_order_id',
        'idempotency_key',
        'signature_type',
        'funder_address',
        'tx_hash',
        'raw_request',
        'raw_response',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'size' => 'float',
            'filled_size' => 'float',
            'signature_type' => 'integer',
            'raw_request' => 'array',
            'raw_response' => 'array',
        ];
    }

    public function polymarketAccount(): BelongsTo
    {
        return $this->belongsTo(PolymarketAccount::class, 'polymarket_account_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
