<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketsJob;
use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PolymarketSyncMarketsCommand extends Command
{
    protected $signature = 'polymarket:sync-markets
        {--queue : Dispatch to queue instead of running inline}
        {--limit=100 : Number of records per request page}
        {--max-pages=10 : Maximum pages fetched in one run}
        {--lock-seconds=900 : Lock duration in seconds for inline execution}';

    protected $description = 'Sync active markets from Polymarket Gamma API';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketGammaService $gammaService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $lockSeconds = max(30, (int) $this->option('lock-seconds'));

        if ((bool) $this->option('queue')) {
            SyncMarketsJob::dispatch($limit, $maxPages);

            $this->info(sprintf(
                'Dispatch sync markets diminta. limit=%d max_pages=%d',
                $limit,
                $maxPages
            ));

            Log::info('Polymarket market sync queued from artisan command', [
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return self::SUCCESS;
        }

        $lockKey = sprintf('polymarket:sync-markets:inline:%d:%d', $limit, $maxPages);
        $result = Cache::lock($lockKey, $lockSeconds)->get(function () use ($gammaService, $limit, $maxPages) {
            Log::info('Polymarket market sync started from artisan command', [
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return $gammaService->syncActiveMarkets($limit, $maxPages);
        });

        if ($result === false) {
            $this->warn(sprintf(
                'Sync markets dilewati karena proses serupa masih berjalan. limit=%d max_pages=%d',
                $limit,
                $maxPages
            ));

            Log::warning('Polymarket market sync skipped because another run still holds the lock', [
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sync markets selesai. inserted=%d updated=%d tokens_upserted=%d pages=%d',
            $result['inserted'],
            $result['updated'],
            $result['tokens_upserted'],
            $result['pages']
        ));

        return self::SUCCESS;
    }
}
