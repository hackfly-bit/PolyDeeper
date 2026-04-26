<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Models\Market;
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
        $market = $this->resolveMarket($conditionId, $tokenId);
        $canonicalMarketId = $market?->condition_id ?? $conditionId ?? 'UNKNOWN';

        // 1. Save Trade to DB
        $trade = WalletTrade::create([
            'wallet_id' => $wallet->id,
            'market_ref_id' => $market?->id,
            'market_id' => $canonicalMarketId,
            'condition_id' => $conditionId,
            'token_id' => $tokenId,
            'side' => $this->tradeData['side'],
            'price' => $this->tradeData['price'],
            'size' => $this->tradeData['size'],
            'traded_at' => Carbon::createFromTimestamp($this->tradeData['timestamp']),
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
