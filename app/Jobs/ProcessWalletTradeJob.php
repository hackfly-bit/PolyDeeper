<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
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
        $walletAddress = $this->tradeData['wallet'];
        $wallet = Wallet::firstOrCreate(
            ['address' => $walletAddress],
            ['weight' => 0.5, 'win_rate' => 0, 'roi' => 0]
        );

        // 1. Save Trade to DB
        $trade = WalletTrade::create([
            'wallet_id' => $wallet->id,
            'market_id' => $this->tradeData['market_id'],
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
        $marketId = $this->tradeData['market_id'];
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
}
