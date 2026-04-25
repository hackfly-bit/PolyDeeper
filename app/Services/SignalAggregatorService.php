<?php

namespace App\Services;

use App\Models\Signal;
use Illuminate\Support\Carbon;

class SignalAggregatorService
{
    protected WalletScoringService $walletScoring;

    public function __construct(WalletScoringService $walletScoring)
    {
        $this->walletScoring = $walletScoring;
    }

    /**
     * Aggregate signals for a specific market within a timeframe.
     */
    public function aggregate(string $marketId, int $seconds = 60): float
    {
        $signals = Signal::where('market_id', $marketId)
            ->where('created_at', '>=', Carbon::now()->subSeconds($seconds))
            ->get();

        $walletScore = 0.0;

        foreach ($signals as $signal) {
            $weight = $this->walletScoring->getWalletWeight($signal->wallet_id);
            $walletScore += ($signal->direction * $signal->strength * $weight);
        }

        return $this->sigmoid($walletScore);
    }

    /**
     * Sigmoid function for normalization.
     */
    private function sigmoid(float $x): float
    {
        return 1 / (1 + exp(-$x));
    }
}