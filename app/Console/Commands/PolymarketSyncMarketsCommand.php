<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketsJob;
use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Console\Command;

class PolymarketSyncMarketsCommand extends Command
{
    protected $signature = 'polymarket:sync-markets
        {--queue : Dispatch to queue instead of running inline}
        {--limit=100 : Number of records per request page}
        {--max-pages=10 : Maximum pages fetched in one run}';

    protected $description = 'Sync active markets from Polymarket Gamma API';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketGammaService $gammaService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxPages = max(1, (int) $this->option('max-pages'));

        if ((bool) $this->option('queue')) {
            SyncMarketsJob::dispatch($limit, $maxPages);
            $this->info('SyncMarketsJob dispatched.');

            return self::SUCCESS;
        }

        $result = $gammaService->syncActiveMarkets($limit, $maxPages);

        $this->info(sprintf(
            'Sync selesai. inserted=%d updated=%d tokens_upserted=%d pages=%d',
            $result['inserted'],
            $result['updated'],
            $result['tokens_upserted'],
            $result['pages']
        ));

        return self::SUCCESS;
    }
}
