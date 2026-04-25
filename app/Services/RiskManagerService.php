<?php

namespace App\Services;

use App\Models\Position;

class RiskManagerService
{
    /**
     * Validate before executing trade.
     * Returns true if valid, false otherwise.
     */
    public function validate(string $marketId, string $action, float $finalScore, float $walletScore): bool
    {
        if ($action === 'SKIP') {
            return false;
        }

        // 1. Exposure (Mock check: if market_exposure > 10% balance)
        if ($this->getMarketExposure($marketId) > 10) {
            return false;
        }

        // 2. Daily Loss (Mock check: if daily_loss > 10%)
        if ($this->getDailyLoss() > 10) {
            return false;
        }

        // 3. Conflict (if abs(wallet_score) < 0.1)
        if (abs($walletScore) < 0.1) {
            return false;
        }

        // 4. Slippage check (Mock check)
        if (!$this->isPriceWithinSlippage($marketId)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate position size based on final score.
     */
    public function calculatePositionSize(float $finalScore, float $balance = 10000): float
    {
        $maxSize = $balance * 0.02; // 2% of balance
        return $maxSize * $finalScore;
    }

    private function getMarketExposure(string $marketId): float
    {
        // Example mock return value in percent
        return 5.0;
    }

    private function getDailyLoss(): float
    {
        // Example mock return value in percent
        return 2.0;
    }

    private function isPriceWithinSlippage(string $marketId): bool
    {
        return true;
    }
}