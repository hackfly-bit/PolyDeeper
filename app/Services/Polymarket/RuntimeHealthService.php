<?php

namespace App\Services\Polymarket;

use App\Models\ExecutionLog;
use App\Models\PolymarketAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RuntimeHealthService
{
    public function recordAuthFailure(PolymarketAccount $account, int $statusCode, string $message): void
    {
        $failureCount = (int) $account->auth_failure_count + 1;

        ExecutionLog::query()->create([
            'stage' => 'runtime_health_auth_failure',
            'market_id' => $account->account_slug,
            'wallet_address' => $account->wallet_address,
            'action' => 'AUTH_FAILURE',
            'status' => 'warning',
            'message' => $message,
            'context' => [
                'account_id' => $account->id,
                'status_code' => $statusCode,
                'failure_count' => $failureCount,
            ],
            'occurred_at' => now(),
        ]);

        $account->update([
            'credential_status' => 'validation_failed',
            'last_error_code' => 'HTTP_'.$statusCode,
            'auth_failure_count' => $failureCount,
        ]);

        if ($failureCount >= 3) {
            Log::warning('Polymarket account candidate revoked (auth failures)', [
                'account_id' => $account->id,
                'status_code' => $statusCode,
                'failure_count' => $failureCount,
            ]);
        }
    }

    public function recordRateLimitHit(PolymarketAccount $account, int $hitCount): void
    {
        $storedHitCount = (int) $account->rate_limit_hit_count + 1;

        ExecutionLog::query()->create([
            'stage' => 'runtime_health_rate_limit',
            'market_id' => $account->account_slug,
            'wallet_address' => $account->wallet_address,
            'action' => 'RATE_LIMIT_HIT',
            'status' => $hitCount >= 3 ? 'warning' : 'info',
            'message' => 'Rate-limit terdeteksi pada endpoint trade account.',
            'context' => [
                'account_id' => $account->id,
                'hits' => $hitCount,
                'stored_hits' => $storedHitCount,
            ],
            'occurred_at' => now(),
        ]);

        $account->update(['rate_limit_hit_count' => $storedHitCount]);
    }

    public function recordTimestampMismatch(PolymarketAccount $account, string $detail): void
    {
        $cacheKey = sprintf('polymarket:timestamp-mismatch:%d', $account->id);
        $count = (int) Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, now()->addMinutes(30));

        ExecutionLog::query()->create([
            'stage' => 'runtime_health_timestamp_mismatch',
            'market_id' => $account->account_slug,
            'wallet_address' => $account->wallet_address,
            'action' => 'TIMESTAMP_MISMATCH',
            'status' => 'warning',
            'message' => 'Kemungkinan mismatch timestamp berulang di request signing.',
            'context' => [
                'account_id' => $account->id,
                'detail' => $detail,
                'occurrences' => $count,
            ],
            'occurred_at' => now(),
        ]);

        if ($count >= 3) {
            Log::warning('Polymarket timestamp mismatch berulang', [
                'account_id' => $account->id,
                'count' => $count,
                'detail' => $detail,
            ]);
        }
    }
}
