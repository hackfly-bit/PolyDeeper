<?php

namespace App\Jobs;

use App\Models\PolymarketAccount;
use App\Services\OrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOpenOrdersJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public function __construct(
        public int $accountId,
        public int $limit = 200
    ) {}

    public function uniqueId(): string
    {
        return sprintf('orders:%d:%d', $this->accountId, $this->limit);
    }

    /**
     * Execute the job.
     */
    public function handle(OrderSyncService $orderSyncService): void
    {
        Log::info('Polymarket order sync started', [
            'account_id' => $this->accountId,
            'limit' => $this->limit,
        ]);

        $account = PolymarketAccount::query()->find($this->accountId);
        if ($account === null || ! $account->is_active) {
            Log::warning('Polymarket order sync skipped because account is missing or inactive', [
                'account_id' => $this->accountId,
            ]);

            return;
        }

        $result = $orderSyncService->syncOpenOrders($this->limit, $account);

        Log::info('Polymarket order sync completed', [
            'account_id' => $this->accountId,
            'limit' => $this->limit,
            'synced' => $result['synced'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Polymarket order sync failed', [
            'account_id' => $this->accountId,
            'limit' => $this->limit,
            'message' => $exception->getMessage(),
        ]);
    }
}
