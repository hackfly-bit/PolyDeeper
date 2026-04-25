<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionLog extends Model
{
    protected $fillable = [
        'stage',
        'market_id',
        'wallet_address',
        'action',
        'status',
        'message',
        'context',
        'execution_time_ms',
        'trade_executed',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
