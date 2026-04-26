<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarketToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_id',
        'token_id',
        'outcome',
        'is_yes',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'is_yes' => 'boolean',
            'raw_payload' => 'array',
        ];
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
