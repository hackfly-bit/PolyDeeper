<?php

namespace App\Jobs;

use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncMarketsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $pageSize = 100,
        public int $maxPages = 10
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PolymarketGammaService $gammaService): void
    {
        $result = $gammaService->syncActiveMarkets($this->pageSize, $this->maxPages);

        Log::info('Polymarket markets synced', $result);
    }
}
