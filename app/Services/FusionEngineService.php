<?php

namespace App\Services;

class FusionEngineService
{
    /**
     * Fuses Wallet Signal and AI Prediction to compute final score and decision.
     */
    public function fuse(float $walletSignal, float $probability, float $confidence): array
    {
        $aiSignal = $probability * $confidence;

        // Dynamic weights based on AI confidence
        if ($confidence > 0.7) {
            $Wa = 0.5;
            $Ww = 0.5;
        } else {
            $Wa = 0.3;
            $Ww = 0.7;
        }

        $finalScore = ($walletSignal * $Ww) + ($aiSignal * $Wa);

        // Determine action
        if ($finalScore > 0.65) {
            $action = 'BUY YES';
        } elseif ($finalScore < 0.35) {
            $action = 'BUY NO';
        } else {
            $action = 'SKIP';
        }

        return [
            'final_score' => $finalScore,
            'action' => $action,
            'wallet_signal' => $walletSignal,
            'ai_signal' => $aiSignal,
        ];
    }
}