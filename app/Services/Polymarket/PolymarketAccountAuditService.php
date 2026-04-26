<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use App\Models\PolymarketAccountAuditLog;

class PolymarketAccountAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(
        PolymarketAccount $account,
        string $action,
        string $status,
        string $message,
        array $context = [],
        string $actor = 'system'
    ): void {
        PolymarketAccountAuditLog::query()->create([
            'polymarket_account_id' => $account->id,
            'action' => $action,
            'status' => $status,
            'message' => $message,
            'context' => $context,
            'actor' => $actor,
            'occurred_at' => now(),
        ]);
    }
}
