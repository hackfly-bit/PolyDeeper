<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WalletTrade;
use App\Services\SignalNormalizerService;
use Illuminate\Support\Facades\Redis;

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
        $wallet = \App\Models\Wallet::firstOrCreate(
            ['address' => $walletAddress],
            ['weight' => 0.5, 'win_rate' => 0, 'roi' => 0]
        );

        // 1. Save Trade to DB
        $trade = WalletTrade::create([
            'wallet_id' => $wallet->id,
            'market_id' => $this->tradeData['market_id'],
            'side'      => $this->tradeData['side'],
            'price'     => $this->tradeData['price'],
            'size'      => $this->tradeData['size'],
            'traded_at' => \Carbon\Carbon::createFromTimestamp($this->tradeData['timestamp']),
        ]);

        // 2. Normalize to Signal
        $signal = $normalizer->normalize($trade);

        // 3. Debounce / Trigger Aggregation
        // We only want to trigger aggregation once every 1-2 seconds per market to avoid queue flooding.
        $marketId = $this->tradeData['market_id'];
        $debounceKey = "aggregator_lock:{$marketId}";

        if (Redis::setnx($debounceKey, 1)) {
            Redis::expire($debounceKey, 2); // 2 seconds lock
            
            // Dispatch Aggregation Job
            AggregateSignalJob::dispatch($marketId)->delay(now()->addSeconds(2));
        }
    }
}