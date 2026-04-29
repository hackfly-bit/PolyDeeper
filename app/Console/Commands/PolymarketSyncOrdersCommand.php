<?php

namespace App\Console\Commands;

use App\Jobs\SyncOpenOrdersJob;
use App\Models\PolymarketAccount;
use App\Services\OrderSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PolymarketSyncOrdersCommand extends Command
{
    protected $signature = 'polymarket:sync-orders
        {--limit=200 : Max order rows to pull}
        {--account= : Account id spesifik}
        {--inline : Run inline sync instead of queue dispatch}
        {--queue : Force queue dispatch mode (default behavior)}
        {--lock-seconds=300 : Lock duration in seconds for inline execution}';

    protected $description = 'Sync status/fills order lokal dari Polymarket CLOB';

    /**
     * Execute the console command.
     */
    public function handle(OrderSyncService $orderSyncService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $accountId = $this->option('account');
        $lockSeconds = max(30, (int) $this->option('lock-seconds'));
        $queueMode = ! (bool) $this->option('inline') || (bool) $this->option('queue');

        $accounts = PolymarketAccount::query()
            ->where('is_active', true)
            ->whereIn('credential_status', ['active', 'needs_rotation'])
            ->when($accountId !== null, function ($query) use ($accountId) {
                $query->where('id', (int) $accountId);
            })
            ->get(['id', 'name', 'wallet_address', 'api_key', 'api_secret', 'api_passphrase', 'credential_status']);

        if ($accounts->isEmpty()) {
            $this->warn('Tidak ada account Polymarket aktif yang cocok untuk disinkronkan.');

            Log::warning('Polymarket order sync skipped because no active accounts matched the command filter', [
                'account_id' => $accountId !== null ? (int) $accountId : null,
                'limit' => $limit,
                'queue' => $queueMode,
            ]);

            return self::SUCCESS;
        }

        $dispatched = 0;
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $synced = 0;

        foreach ($accounts as $account) {
            if (! $this->hasOrderSyncCredentials($account)) {
                $skipped++;

                $this->warn(sprintf(
                    'Sync order dilewati untuk account #%d (%s) karena credential L2 belum lengkap.',
                    $account->id,
                    $account->name
                ));

                Log::warning('Polymarket order sync skipped because account credentials are incomplete', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'credential_status' => $account->credential_status,
                    'limit' => $limit,
                    'queue' => $queueMode,
                ]);

                continue;
            }

            if ($queueMode) {
                SyncOpenOrdersJob::dispatch($account->id, $limit);
                $dispatched++;

                $this->line(sprintf(
                    'Queue sync order diminta untuk account #%d (%s).',
                    $account->id,
                    $account->name
                ));

                Log::info('Polymarket order sync queued from artisan command', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'limit' => $limit,
                ]);

                continue;
            }

            try {
                $lockKey = sprintf('polymarket:sync-orders:inline:%d', $account->id);
                $result = Cache::lock($lockKey, $lockSeconds)->get(function () use ($orderSyncService, $account, $limit) {
                    Log::info('Polymarket order sync started from artisan command', [
                        'account_id' => $account->id,
                        'account_name' => $account->name,
                        'limit' => $limit,
                    ]);

                    return $orderSyncService->syncOpenOrders($limit, $account);
                });
            } catch (Throwable $exception) {
                $failed++;

                $this->error(sprintf(
                    'Sync order gagal untuk account #%d (%s): %s',
                    $account->id,
                    $account->name,
                    $exception->getMessage()
                ));

                Log::error('Polymarket order sync failed from artisan command', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'limit' => $limit,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($result === false) {
                $skipped++;

                $this->warn(sprintf(
                    'Sync order dilewati untuk account #%d (%s) karena proses serupa masih berjalan.',
                    $account->id,
                    $account->name
                ));

                Log::warning('Polymarket order sync skipped because another run still holds the lock', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'limit' => $limit,
                ]);

                continue;
            }

            $processed++;
            $synced += (int) ($result['synced'] ?? 0);

            $this->info(sprintf(
                'Sync order selesai untuk account #%d (%s). synced=%d',
                $account->id,
                $account->name,
                $result['synced'] ?? 0
            ));
        }

        if ($queueMode) {
            $this->info(sprintf(
                'Dispatch sync order diminta untuk %d account aktif.',
                $dispatched
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sync order inline selesai. processed=%d skipped=%d failed=%d total_synced=%d',
            $processed,
            $skipped,
            $failed,
            $synced
        ));

        return self::SUCCESS;
    }

    private function hasOrderSyncCredentials(PolymarketAccount $account): bool
    {
        return filled($account->wallet_address)
            && filled($account->api_key)
            && filled($account->api_secret)
            && filled($account->api_passphrase);
    }
}
