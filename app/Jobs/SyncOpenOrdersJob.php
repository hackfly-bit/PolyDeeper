<?php

namespace App\Jobs;

use App\Models\PolymarketAccount;
use App\Services\OrderSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncOpenOrdersJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $accountId,
        public int $limit = 200
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OrderSyncService $orderSyncService): void
    {
        $account = PolymarketAccount::query()->find($this->accountId);
        if ($account === null || ! $account->is_active) {
            return;
        }

        $orderSyncService->syncOpenOrders($this->limit, $account);
    }
}
