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
        {--inline : Run sync inline instead of dispatching to queue}
        {--queue : Force queue dispatch mode (default behavior)}
        {--all : Sync all active markets (disable watched-only mode)}
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
        $queueMode = ! (bool) $this->option('inline') || (bool) $this->option('queue');
        $watchedOnly = ! (bool) $this->option('all');

        if ($queueMode) {
            SyncMarketsJob::dispatch($limit, $maxPages, $watchedOnly);

            $this->info(sprintf(
                'Dispatch sync markets diminta. scope=%s limit=%d max_pages=%d',
                $watchedOnly ? 'watched-only' : 'all',
                $limit,
                $maxPages
            ));

            Log::info('Polymarket market sync queued from artisan command', [
                'watched_only' => $watchedOnly,
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return self::SUCCESS;
        }

        $lockKey = sprintf('polymarket:sync-markets:inline:%s:%d:%d', $watchedOnly ? 'watched' : 'all', $limit, $maxPages);
        $result = Cache::lock($lockKey, $lockSeconds)->get(function () use ($gammaService, $limit, $maxPages, $watchedOnly) {
            Log::info('Polymarket market sync started from artisan command', [
                'watched_only' => $watchedOnly,
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return $gammaService->syncActiveMarkets($limit, $maxPages, $watchedOnly);
        });

        if ($result === false) {
            $this->warn(sprintf(
                'Sync markets dilewati karena proses serupa masih berjalan. limit=%d max_pages=%d',
                $limit,
                $maxPages
            ));

            Log::warning('Polymarket market sync skipped because another run still holds the lock', [
                'watched_only' => $watchedOnly,
                'page_size' => $limit,
                'max_pages' => $maxPages,
            ]);

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sync markets selesai. scope=%s inserted=%d updated=%d tokens_upserted=%d pages=%d',
            $watchedOnly ? 'watched-only' : 'all',
            $result['inserted'],
            $result['updated'],
            $result['tokens_upserted'],
            $result['pages']
        ));

        return self::SUCCESS;
    }
}
