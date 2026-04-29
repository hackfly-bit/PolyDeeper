<?php

namespace App\Jobs;

use App\Models\WalletTrade;
use App\Services\Polymarket\PolymarketGammaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncMarketFromTradeConditionJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 900;

    public function __construct(public string $conditionId) {}

    public function uniqueId(): string
    {
        return 'sync-market-condition:'.$this->conditionId;
    }

    public function handle(PolymarketGammaService $gammaService): void
    {
        $sync = $gammaService->syncMarketsByConditionIds([$this->conditionId]);

        $linkedTrades = WalletTrade::query()
            ->where('condition_id', $this->conditionId)
            ->whereNull('market_ref_id')
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id');
            })
            ->update([
                'market_ref_id' => DB::raw('(SELECT id FROM markets WHERE markets.condition_id = wallet_trades.condition_id LIMIT 1)'),
            ]);

        Log::info('Polymarket market condition sync job completed', [
            'condition_id' => $this->conditionId,
            'requested' => $sync['requested'] ?? 0,
            'found' => $sync['found'] ?? 0,
            'inserted' => $sync['inserted'] ?? 0,
            'updated' => $sync['updated'] ?? 0,
            'tokens_upserted' => $sync['tokens_upserted'] ?? 0,
            'missing' => $sync['missing'] ?? 0,
            'linked_trades' => $linkedTrades,
        ]);
    }
}
