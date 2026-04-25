<?php

namespace App\Services\AiPrediction;

class HeuristicPredictor implements AiPredictorInterface
{
    /**
     * Rule-based prediction algorithm as a fallback for LLM.
     */
    public function predict(string $marketId, array $features): array
    {
        $price = $features['price'] ?? 0.5;
        $momentum = $features['momentum'] ?? 0.0;
        $volume = $features['volume'] ?? 1000;

        // Basic heuristic
        $probability = $price + ($momentum * 0.5);
        $probability = max(0, min(1, $probability)); // clamp between 0 and 1

        // Confidence based on volume
        $confidence = min($volume / 50000, 1.0); // max confidence at 50k volume
        
        // Ensure confidence is decent for rule-based approach
        $confidence = max(0.4, $confidence);

        return [
            'probability' => $probability,
            'confidence' => $confidence,
        ];
    }
}