<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\SignalAggregatorService;
use Illuminate\Support\Facades\Log;

class AggregateSignalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $marketId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $marketId)
    {
        $this->marketId = $marketId;
    }

    /**
     * Execute the job.
     */
    public function handle(SignalAggregatorService $aggregator): void
    {
        Log::info("Aggregating signals", ['market_id' => $this->marketId]);
        
        $walletSignal = $aggregator->aggregate($this->marketId, 60);

        Log::info("Wallet Signal Calculated", ['market_id' => $this->marketId, 'wallet_signal' => $walletSignal]);

        // Trigger Fusion Decision immediately
        FusionDecisionJob::dispatch($this->marketId, $walletSignal);
    }
}