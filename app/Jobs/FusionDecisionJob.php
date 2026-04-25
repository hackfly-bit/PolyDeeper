<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Services\AiPrediction\AiPredictorInterface;
use App\Services\FusionEngineService;
use App\Services\RiskManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
        Log::info('Fusion Decision Started', ['market_id' => $this->marketId]);
        ExecutionLog::create([
            'stage' => 'fusion_started',
            'market_id' => $this->marketId,
            'action' => 'FUSION_DECISION',
            'status' => 'info',
            'message' => 'Fusion decision process started.',
            'context' => ['wallet_signal' => $this->walletSignal],
            'occurred_at' => now(),
        ]);

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
        Log::info('Decision Computed', $decision);

        $action = $decision['action'];
        $finalScore = $decision['final_score'];

        ExecutionLog::create([
            'stage' => 'fusion_decision',
            'market_id' => $this->marketId,
            'action' => $action,
            'status' => 'info',
            'message' => 'Fusion score computed.',
            'context' => [
                'wallet_signal' => $this->walletSignal,
                'ai_probability' => $aiResult['probability'],
                'ai_confidence' => $aiResult['confidence'],
                'final_score' => $finalScore,
            ],
            'occurred_at' => now(),
        ]);

        // 3. Risk Check
        $isValid = $riskManager->validate($this->marketId, $action, $finalScore, $this->walletSignal);

        if (! $isValid) {
            Log::info('Trade skipped or failed risk checks', ['action' => $action]);
            ExecutionLog::create([
                'stage' => 'risk_rejected',
                'market_id' => $this->marketId,
                'action' => $action,
                'status' => 'warning',
                'message' => 'Trade rejected by risk checks or SKIP action.',
                'context' => [
                    'final_score' => $finalScore,
                    'wallet_signal' => $this->walletSignal,
                ],
                'occurred_at' => now(),
            ]);

            return;
        }

        // 4. Calculate Size
        $positionSize = $riskManager->calculatePositionSize($finalScore);

        // Extract side from action (e.g., "BUY YES" -> "YES")
        $side = str_replace('BUY ', '', $action);

        ExecutionLog::create([
            'stage' => 'risk_passed',
            'market_id' => $this->marketId,
            'action' => $action,
            'status' => 'success',
            'message' => 'Risk checks passed and trade execution will be dispatched.',
            'context' => [
                'side' => $side,
                'position_size' => $positionSize,
                'entry_price' => $features['price'],
                'final_score' => $finalScore,
            ],
            'occurred_at' => now(),
        ]);

        // 5. Execute Trade
        ExecuteTradeJob::dispatch($this->marketId, $side, $positionSize, $features['price']);
    }
}
