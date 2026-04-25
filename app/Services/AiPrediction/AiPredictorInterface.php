<?php

namespace App\Services\AiPrediction;

interface AiPredictorInterface
{
    /**
     * Predict probability and confidence for a market.
     * Returns associative array: ['probability' => float, 'confidence' => float]
     */
    public function predict(string $marketId, array $features): array;
}