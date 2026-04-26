<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Market extends Model
{
    use HasFactory;

    protected $fillable = [
        'condition_id',
        'slug',
        'question',
        'description',
        'active',
        'closed',
        'end_date',
        'minimum_tick_size',
        'neg_risk',
        'raw_payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'closed' => 'boolean',
            'neg_risk' => 'boolean',
            'minimum_tick_size' => 'float',
            'raw_payload' => 'array',
            'end_date' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(MarketToken::class);
    }
}
