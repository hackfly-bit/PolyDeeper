<?php

namespace App\Services\Backtesting;

use App\Models\WalletTrade;
use App\Models\Wallet;
use App\Models\Signal;
use App\Services\SignalNormalizerService;
use App\Services\SignalAggregatorService;
use App\Services\FusionEngineService;
use App\Services\AiPrediction\AiPredictorInterface;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BacktestEngine
{
    protected float $virtualBalance = 10000.0;
    protected array $virtualPositions = [];
    protected array $tradeHistory = [];

    protected SignalNormalizerService $normalizer;
    protected SignalAggregatorService $aggregator;
    protected FusionEngineService $fusionEngine;
    protected AiPredictorInterface $aiPredictor;

    public function __construct(
        SignalNormalizerService $normalizer,
        SignalAggregatorService $aggregator,
        FusionEngineService $fusionEngine,
        AiPredictorInterface $aiPredictor
    ) {
        $this->normalizer = $normalizer;
        $this->aggregator = $aggregator;
        $this->fusionEngine = $fusionEngine;
        $this->aiPredictor = $aiPredictor;
    }

    /**
     * Run the backtest on a given set of JSON trade data
     */
    public function run(array $historicalTrades)
    {
        Log::info("Starting Backtest Engine with " . count($historicalTrades) . " trades...");

        // Reset Database Tables (Optional, just for clean testing environment)
        WalletTrade::truncate();
        Signal::truncate();
        
        $totalProcessed = 0;

        foreach ($historicalTrades as $tradeData) {
            $this->processHistoricalTrade($tradeData);
            $totalProcessed++;
        }

        return $this->getResults($totalProcessed);
    }

    private function processHistoricalTrade(array $tradeData)
    {
        // Setup mock time based on historical trade timestamp
        $tradeTime = Carbon::createFromTimestamp($tradeData['timestamp']);
        Carbon::setTestNow($tradeTime); // Set Laravel's 'now()' to this specific historical moment

        // 1. Ensure Wallet exists
        $wallet = Wallet::firstOrCreate(
            ['address' => $tradeData['wallet']],
            ['weight' => $tradeData['weight'] ?? 0.5, 'win_rate' => 0, 'roi' => 0]
        );

        // 2. Save Trade
        $trade = WalletTrade::create([
            'wallet_id' => $wallet->id,
            'market_id' => $tradeData['market_id'],
            'side'      => $tradeData['side'],
            'price'     => $tradeData['price'],
            'size'      => $tradeData['size'],
            'traded_at' => $tradeTime,
        ]);

        // 3. Normalize
        $signal = $this->normalizer->normalize($trade);

        // 4. Aggregate
        $walletSignal = $this->aggregator->aggregate($tradeData['market_id'], 60);

        // 5. Predict (Mock AI Features using current price)
        $features = [
            'price' => $tradeData['price'],
            'momentum' => 0.05,
            'volume' => 10000,
        ];
        $aiResult = $this->aiPredictor->predict($tradeData['market_id'], $features);

        // 6. Fuse
        $decision = $this->fusionEngine->fuse($walletSignal, $aiResult['probability'], $aiResult['confidence']);

        // 7. Virtual Execution
        if ($decision['action'] !== 'SKIP') {
            $this->executeVirtualTrade($tradeData['market_id'], $decision, $tradeData['price']);
        }

        Carbon::setTestNow(); // Reset time back to normal
    }

    private function executeVirtualTrade(string $marketId, array $decision, float $price)
    {
        // Simple Virtual Risk Manager
        $maxSize = $this->virtualBalance * 0.02; // 2% of balance
        $positionSize = $maxSize * $decision['final_score'];

        $side = str_replace('BUY ', '', $decision['action']);

        if ($this->virtualBalance >= $positionSize) {
            $this->virtualBalance -= $positionSize;

            $this->virtualPositions[] = [
                'market_id' => $marketId,
                'side' => $side,
                'size' => $positionSize,
                'entry_price' => $price,
                'timestamp' => Carbon::now()->toDateTimeString(),
            ];

            $this->tradeHistory[] = [
                'market_id' => $marketId,
                'side' => $side,
                'size' => $positionSize,
                'entry_price' => $price,
                'final_score' => $decision['final_score'],
                'wallet_signal' => $decision['wallet_signal'],
                'ai_signal' => $decision['ai_signal'],
            ];
        }
    }

    public function getResults(int $totalProcessed): array
    {
        return [
            'initial_balance' => 10000.0,
            'final_balance' => $this->virtualBalance,
            'open_positions_count' => count($this->virtualPositions),
            'total_trades_executed' => count($this->tradeHistory),
            'historical_trades_processed' => $totalProcessed,
            'trade_history' => $this->tradeHistory,
        ];
    }
}