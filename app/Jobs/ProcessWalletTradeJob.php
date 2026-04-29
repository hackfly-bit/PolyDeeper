<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Models\Market;
use App\Models\MarketToken;
use App\Models\Wallet;
use App\Models\WalletTrade;
use App\Services\SignalNormalizerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ProcessWalletTradeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $tradeData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $tradeData)
    {
        $this->tradeData = $tradeData;
    }

    /**
     * Execute the job.
     */
    public function handle(SignalNormalizerService $normalizer): void
    {
        $dedupeKey = $this->buildTradeDedupeKey();
        if (! Redis::setnx($dedupeKey, 1)) {
            return;
        }
        Redis::expire($dedupeKey, 3600);

        $walletAddress = $this->tradeData['wallet'];
        $wallet = Wallet::firstOrCreate(
            ['address' => $walletAddress],
            ['weight' => 0.5, 'win_rate' => 0, 'roi' => 0]
        );

        $conditionId = $this->tradeData['condition_id'] ?? $this->tradeData['market_id'] ?? null;
        $tokenId = $this->tradeData['token_id'] ?? null;
        $market = $this->syncMarketFromTradeData($conditionId, $tokenId);
        $canonicalMarketId = $market?->condition_id ?? $conditionId ?? 'UNKNOWN';
        $tradedAt = Carbon::createFromTimestamp((int) $this->tradeData['timestamp']);

        // 1. Save/Sync trade to DB (idempotent)
        $trade = WalletTrade::query()->updateOrCreate([
            'wallet_id' => $wallet->id,
            'market_id' => $canonicalMarketId,
            'condition_id' => $conditionId,
            'token_id' => $tokenId,
            'side' => $this->tradeData['side'],
            'price' => $this->tradeData['price'],
            'size' => $this->tradeData['size'],
            'traded_at' => $tradedAt,
        ], [
            'market_ref_id' => $market?->id,
        ]);

        ExecutionLog::create([
            'stage' => 'trade_saved',
            'market_id' => $trade->market_id,
            'wallet_address' => $walletAddress,
            'action' => 'TRADE_INGESTED',
            'status' => 'success',
            'message' => 'Wallet trade persisted to database.',
            'context' => [
                'trade_id' => $trade->id,
                'side' => $trade->side,
                'price' => $trade->price,
                'size' => $trade->size,
            ],
            'occurred_at' => now(),
        ]);

        // 2. Normalize to Signal
        $signal = $normalizer->normalize($trade);

        ExecutionLog::create([
            'stage' => 'signal_normalized',
            'market_id' => $signal->market_id,
            'wallet_address' => $walletAddress,
            'action' => $signal->direction > 0 ? 'SIGNAL_YES' : 'SIGNAL_NO',
            'status' => 'success',
            'message' => 'Trade normalized into signal.',
            'context' => [
                'signal_id' => $signal->id,
                'strength' => $signal->strength,
                'direction' => $signal->direction,
            ],
            'occurred_at' => now(),
        ]);

        // 3. Debounce / Trigger Aggregation
        // We only want to trigger aggregation once every 1-2 seconds per market to avoid queue flooding.
        $marketId = $canonicalMarketId;
        $debounceKey = "aggregator_lock:{$marketId}";

        try {
            if (! Redis::setnx($debounceKey, 1)) {
                ExecutionLog::create([
                    'stage' => 'aggregation_debounced',
                    'market_id' => $marketId,
                    'wallet_address' => $walletAddress,
                    'action' => 'DEBOUNCED',
                    'status' => 'info',
                    'message' => 'Aggregation skipped due to short debounce window.',
                    'occurred_at' => now(),
                ]);

                return;
            }

            Redis::expire($debounceKey, 2); // 2 seconds lock
            AggregateSignalJob::dispatch($marketId)->delay(now()->addSeconds(2));

            ExecutionLog::create([
                'stage' => 'aggregation_dispatched',
                'market_id' => $marketId,
                'wallet_address' => $walletAddress,
                'action' => 'AGGREGATE_SIGNAL',
                'status' => 'info',
                'message' => 'Aggregation job dispatched with Redis debounce lock.',
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            AggregateSignalJob::dispatch($marketId)->delay(now()->addSeconds(1));

            ExecutionLog::create([
                'stage' => 'aggregation_dispatch_fallback',
                'market_id' => $marketId,
                'wallet_address' => $walletAddress,
                'action' => 'AGGREGATE_SIGNAL',
                'status' => 'warning',
                'message' => 'Redis unavailable, aggregation dispatched without debounce.',
                'context' => [
                    'error' => $exception->getMessage(),
                ],
                'occurred_at' => now(),
            ]);
        }
    }

    private function resolveMarket(?string $conditionId, ?string $tokenId): ?Market
    {
        if ($conditionId === null && $tokenId === null) {
            return null;
        }

        return Market::query()
            ->when($conditionId !== null, function ($query) use ($conditionId) {
                $query->where('condition_id', $conditionId);
            })
            ->when($tokenId !== null, function ($query) use ($tokenId) {
                $query->whereHas('tokens', function ($tokenQuery) use ($tokenId) {
                    $tokenQuery->where('token_id', $tokenId);
                });
            })
            ->first();
    }

    private function syncMarketFromTradeData(?string $conditionId, ?string $tokenId): ?Market
    {
        $market = $this->resolveMarket($conditionId, $tokenId);

        if ($market === null && $conditionId !== null && $conditionId !== '') {
            $market = Market::query()->updateOrCreate(
                ['condition_id' => $conditionId],
                [
                    'slug' => $this->tradeData['market_slug'] ?? null,
                    'title' => $this->tradeData['market_title'] ?? null,
                    'question' => $this->tradeData['question'] ?? null,
                    'description' => $this->tradeData['description'] ?? null,
                    'raw_payload' => $this->tradeData,
                    'last_synced_at' => now(),
                ]
            );
        }

        if ($market !== null && $tokenId !== null && $tokenId !== '') {
            $outcome = strtoupper((string) ($this->tradeData['outcome'] ?? $this->tradeData['side'] ?? ''));

            MarketToken::query()->updateOrCreate(
                ['token_id' => $tokenId],
                [
                    'market_id' => $market->id,
                    'outcome' => $outcome !== '' ? $outcome : null,
                    'is_yes' => $outcome === 'YES',
                    'raw_payload' => ['source' => 'trade_ingest', 'trade' => $this->tradeData],
                ]
            );
        }

        return $market;
    }

    private function buildTradeDedupeKey(): string
    {
        $seed = implode('|', [
            $this->tradeData['tx_hash'] ?? '',
            $this->tradeData['wallet'] ?? '',
            $this->tradeData['market_id'] ?? '',
            $this->tradeData['condition_id'] ?? '',
            $this->tradeData['token_id'] ?? '',
            $this->tradeData['side'] ?? '',
            (string) ($this->tradeData['size'] ?? ''),
            (string) ($this->tradeData['price'] ?? ''),
            (string) ($this->tradeData['timestamp'] ?? ''),
        ]);

        return 'wallet_trade_dedupe:'.sha1($seed);
    }
}
