<?php

namespace App\Services;

use App\Models\Signal;
use App\Models\WalletTrade;

class SignalNormalizerService
{
    protected WalletScoringService $walletScoring;

    public function __construct(WalletScoringService $walletScoring)
    {
        $this->walletScoring = $walletScoring;
    }

    /**
     * Normalize an incoming trade into a standardized Signal.
     */
    public function normalize(WalletTrade $trade, float $avgWalletSize = 1000.0): Signal
    {
        // Direction: YES = +1, NO = -1
        $direction = strtoupper($trade->side) === 'YES' ? 1 : -1;

        // Strength: normalized size
        $strength = min($trade->size / $avgWalletSize, 1.0);

        $signal = Signal::create([
            'market_id' => $trade->market_id,
            'direction' => $direction,
            'strength' => $strength,
            'wallet_id' => $trade->wallet_id,
        ]);

        return $signal;
    }
}