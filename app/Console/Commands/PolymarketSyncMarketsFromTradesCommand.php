<?php

namespace App\Console\Commands;

use App\Jobs\SyncMarketFromTradeConditionJob;
use App\Models\Market;
use App\Models\MarketToken;
use App\Models\WalletTrade;
use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PolymarketSyncMarketsFromTradesCommand extends Command
{
    protected $signature = 'polymarket:sync-markets-from-trades
        {--fresh : Hapus data lokal markets dan market_tokens sebelum sinkron}
        {--queue : Dispatch per condition_id ke job untuk concurrency}
        {--condition-id= : Sinkron hanya 1 condition_id spesifik untuk testing}
        {--limit=0 : Batasi jumlah condition_id dari wallet_trades (0 = semua)}
        {--lock-seconds=900 : Lock duration in seconds}';

    protected $description = 'Sync tabel markets berdasarkan condition_id di wallet_trades menggunakan data terbaru dari Polymarket';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketGammaService $gammaService): int
    {
        $lockSeconds = max(30, (int) $this->option('lock-seconds'));
        $fresh = (bool) $this->option('fresh');
        $queueMode = (bool) $this->option('queue');
        $conditionId = trim((string) ($this->option('condition-id') ?? ''));
        $limit = max(0, (int) $this->option('limit'));
        $lockKey = sprintf(
            'polymarket:sync-markets-from-trades:%s:%s',
            $fresh ? 'fresh' : 'upsert',
            $queueMode ? 'queue' : 'inline'
        );

        $result = Cache::lock($lockKey, $lockSeconds)->get(function () use ($gammaService, $fresh, $queueMode, $conditionId, $limit) {
            if ($conditionId !== '') {
                $conditionIds = collect([$conditionId]);
            } else {
                $query = WalletTrade::query()
                    ->select('condition_id')
                    ->whereNotNull('condition_id')
                    ->where('condition_id', '!=', '')
                    ->groupBy('condition_id')
                    ->orderByRaw('MAX(traded_at) DESC');

                if ($limit > 0) {
                    $query->limit($limit);
                }

                $conditionIds = $query->pluck('condition_id');
            }

            if ($conditionIds->isEmpty()) {
                return null;
            }

            if ($fresh) {
                DB::transaction(function (): void {
                    MarketToken::query()->delete();
                    Market::query()->delete();
                });
            }

            if ($queueMode) {
                $conditionIds->each(function (string $id): void {
                    SyncMarketFromTradeConditionJob::dispatch($id);
                });

                return [
                    'queued' => $conditionIds->count(),
                    'mode' => 'queue',
                ];
            }

            $sync = $gammaService->syncMarketsByConditionIds($conditionIds);

            $linkedTrades = WalletTrade::query()
                ->whereNull('market_ref_id')
                ->whereNotNull('condition_id')
                ->where('condition_id', '!=', '')
                ->whereExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('markets')
                        ->whereColumn('markets.condition_id', 'wallet_trades.condition_id');
                })
                ->update([
                    'market_ref_id' => DB::raw('(SELECT id FROM markets WHERE markets.condition_id = wallet_trades.condition_id LIMIT 1)'),
                ]);

            return [
                ...$sync,
                'linked_trades' => $linkedTrades,
            ];
        });

        if ($result === false) {
            $this->warn('Sync market dari trade dilewati karena proses lain masih berjalan.');

            return self::SUCCESS;
        }

        if ($result === null) {
            $this->warn('Tidak ada condition_id di wallet_trades. Tidak ada market yang bisa disinkron.');

            return self::SUCCESS;
        }

        if (($result['mode'] ?? null) === 'queue') {
            $this->info(sprintf(
                'Sync market dari trade didispatch ke queue. queued=%d mode=queue',
                $result['queued']
            ));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Sync market dari trade selesai. requested=%d found=%d missing=%d inserted=%d updated=%d tokens_upserted=%d linked_trades=%d mode=%s',
            $result['requested'],
            $result['found'],
            $result['missing'],
            $result['inserted'],
            $result['updated'],
            $result['tokens_upserted'],
            $result['linked_trades'],
            $fresh ? 'fresh' : 'upsert'
        ));

        Log::info('Polymarket sync markets from trades completed', [
            'mode' => $fresh ? 'fresh' : 'upsert',
            'requested' => $result['requested'],
            'found' => $result['found'],
            'missing' => $result['missing'],
            'inserted' => $result['inserted'],
            'updated' => $result['updated'],
            'tokens_upserted' => $result['tokens_upserted'],
            'linked_trades' => $result['linked_trades'],
        ]);

        return self::SUCCESS;
    }
}
