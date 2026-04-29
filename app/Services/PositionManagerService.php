<?php

namespace App\Services;

use App\Models\MarketToken;
use App\Models\Position;
use Illuminate\Support\Facades\Log;
use App\Services\AiPrediction\AiPredictorInterface;

class PositionManagerService
{
    protected AiPredictorInterface $aiPredictor;

    public function __construct(AiPredictorInterface $aiPredictor)
    {
        $this->aiPredictor = $aiPredictor;
    }

    /**
     * Track all positions and apply exit rules.
     */
    public function monitorAndManage(): void
    {
        $openPositions = Position::where('status', 'open')->get();

        foreach ($openPositions as $position) {
            $this->evaluateExitRules($position);
        }
    }

    private function evaluateExitRules(Position $position): void
    {
        $marketId = $position->market_id;

        // Rule 1: Exit by wallet (if 70% of tracked wallets exit)
        if ($this->hasWalletsExited($marketId, 0.7)) {
            $this->closePosition($position, 'Wallet exit threshold reached');
            return;
        }

        // Rule 2: Exit by AI (if probability drops significantly)
        // We get current market features
        $prediction = $this->aiPredictor->predict($marketId, [
            'price' => 0.5,
            'momentum' => -0.2,
            'volume' => 15000,
        ]);

        if ($prediction['probability'] < 0.3) {
            $this->reducePosition($position, 'AI probability dropped drastically');
            return;
        }

        // Rule 3: Time decay (if event is less than 1 hour away)
        if ($this->isEventEndingSoon($marketId, 1)) {
            $this->reducePosition($position, 'Time decay (event < 1 hour)');
            return;
        }
    }

    private function hasWalletsExited(string $marketId, float $threshold): bool
    {
        // Mock check
        return false;
    }

    private function isEventEndingSoon(string $marketId, int $hours): bool
    {
        // Mock check
        return false;
    }

    private function closePosition(Position $position, string $reason): void
    {
        Log::info("Closing position", ['position_id' => $position->id, 'reason' => $reason]);

        $entryPrice = (float) $position->entry_price;
        $exitPrice = $this->resolveExitPrice($position) ?? $entryPrice;
        $size = (float) $position->size;
        $isYesSide = strtoupper((string) $position->side) === 'YES';
        $pnlUsd = $isYesSide
            ? ($exitPrice - $entryPrice) * $size
            : ($entryPrice - $exitPrice) * $size;
        $normalizedPnl = round($pnlUsd, 2);
        $outcome = 'breakeven';
        if ($normalizedPnl > 0.01) {
            $outcome = 'win';
        } elseif ($normalizedPnl < -0.01) {
            $outcome = 'loss';
        }

        $position->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_pnl_usd' => $normalizedPnl,
            'outcome' => $outcome,
            'exit_reason' => $reason,
        ]);

        // In reality, this would trigger TradeExecutor to sell the CTF token
    }

    private function reducePosition(Position $position, string $reason): void
    {
        Log::info("Reducing exposure on position", ['position_id' => $position->id, 'reason' => $reason]);

        $position->update(['size' => $position->size / 2]);

        // In reality, this would trigger TradeExecutor to sell part of the CTF token
    }

    private function resolveExitPrice(Position $position): ?float
    {
        $tokenId = (string) ($position->token_id ?? '');
        if ($tokenId === '') {
            return null;
        }

        $token = MarketToken::query()
            ->where('token_id', $tokenId)
            ->first();
        if ($token === null) {
            return null;
        }

        $payload = is_array($token->raw_payload) ? $token->raw_payload : [];
        $candidateKeys = [
            'price',
            'lastPrice',
            'last_price',
            'bestBid',
            'best_bid',
            'bid',
            'mid',
            'midPrice',
            'mid_price',
        ];
        foreach ($candidateKeys as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }
}
