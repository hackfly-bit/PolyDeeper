<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\AiPrediction\AiPredictorInterface;
use App\Services\FusionEngineService;
use App\Services\RiskManagerService;
use Illuminate\Support\Facades\Log;

class FusionDecisionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $marketId;
    protected float $walletSignal;

    public function __construct(string $marketId, float $walletSignal)
    {
        $this->marketId = $marketId;
        $this->walletSignal = $walletSignal;
    }

    public function handle(
        AiPredictorInterface $aiPredictor,
        FusionEngineService $fusionEngine,
        RiskManagerService $riskManager
    ): void {
        Log::info("Fusion Decision Started", ['market_id' => $this->marketId]);

        // 1. Get AI Prediction
        // Mock features for AI prediction
        $features = [
            'price' => 0.5, // should be dynamically fetched
            'momentum' => 0.05,
            'volume' => 12000,
        ];
        $aiResult = $aiPredictor->predict($this->marketId, $features);

        // 2. Fuse signals
        $decision = $fusionEngine->fuse($this->walletSignal, $aiResult['probability'], $aiResult['confidence']);
        
        Log::info("Decision Computed", $decision);

        $action = $decision['action'];
        $finalScore = $decision['final_score'];

        // 3. Risk Check
        $isValid = $riskManager->validate($this->marketId, $action, $finalScore, $this->walletSignal);

        if (!$isValid) {
            Log::info("Trade skipped or failed risk checks", ['action' => $action]);
            return;
        }

        // 4. Calculate Size
        $positionSize = $riskManager->calculatePositionSize($finalScore);

        // Extract side from action (e.g., "BUY YES" -> "YES")
        $side = str_replace('BUY ', '', $action);

        // 5. Execute Trade
        ExecuteTradeJob::dispatch($this->marketId, $side, $positionSize, $features['price']);
    }
}