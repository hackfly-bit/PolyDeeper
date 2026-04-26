<?php

namespace App\Console\Commands;

use App\Jobs\SyncOpenOrdersJob;
use App\Models\PolymarketAccount;
use Illuminate\Console\Command;

class PolymarketSyncOrdersCommand extends Command
{
    protected $signature = 'polymarket:sync-orders
        {--limit=200 : Max order rows to pull}
        {--account= : Account id spesifik}
        {--queue : Dispatch per-account job ke queue}';

    protected $description = 'Sync status/fills order lokal dari Polymarket CLOB';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $accountId = $this->option('account');

        $accounts = PolymarketAccount::query()
            ->where('is_active', true)
            ->when($accountId !== null, function ($query) use ($accountId) {
                $query->where('id', (int) $accountId);
            })
            ->pluck('id');

        foreach ($accounts as $id) {
            if ((bool) $this->option('queue')) {
                SyncOpenOrdersJob::dispatch($id, $limit);
                continue;
            }

            SyncOpenOrdersJob::dispatchSync($id, $limit);
        }

        $this->info('Sync order account dijalankan: '.$accounts->count());

        return self::SUCCESS;
    }
}
