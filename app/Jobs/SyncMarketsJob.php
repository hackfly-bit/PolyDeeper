<?php

namespace App\Jobs;

use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMarketsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function __construct(
        public int $pageSize = 100,
        public int $maxPages = 10
    ) {}

    public function uniqueId(): string
    {
        return sprintf('markets:%d:%d', $this->pageSize, $this->maxPages);
    }

    /**
     * Execute the job.
     */
    public function handle(PolymarketGammaService $gammaService): void
    {
        Log::info('Polymarket market sync started', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
        ]);

        $result = $gammaService->syncActiveMarkets($this->pageSize, $this->maxPages);

        Log::info('Polymarket market sync completed', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'tokens_upserted' => $result['tokens_upserted'],
            'pages' => $result['pages'],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Polymarket market sync failed', [
            'page_size' => $this->pageSize,
            'max_pages' => $this->maxPages,
            'message' => $exception->getMessage(),
        ]);
    }
}
