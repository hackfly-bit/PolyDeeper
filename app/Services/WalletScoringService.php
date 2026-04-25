<?php

namespace App\Services;

use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;

class WalletScoringService
{
    /**
     * Get the current score/weight of a wallet.
     * Caches the weight in Redis to optimize lookup.
     */
    public function getWalletWeight(int $walletId): float
    {
        if (app()->environment('testing') || app()->runningInConsole()) {
            $wallet = Wallet::find($walletId);
            return $wallet ? $wallet->weight : 0.0;
        }

        return Cache::remember("wallet_score:{$walletId}", 60, function () use ($walletId) {
            $wallet = Wallet::find($walletId);
            if (!$wallet) {
                return 0.0;
            }

            // weight = f(win_rate, roi, consistency, recency)
            // Here we use the predefined weight, which can be updated periodically.
            return $wallet->weight;
        });
    }
}