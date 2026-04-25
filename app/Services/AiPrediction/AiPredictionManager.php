<?php

namespace App\Services\AiPrediction;

use Illuminate\Support\Manager;

class AiPredictionManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return config('services.ai.default', 'heuristic');
    }

    public function createLlmDriver(): AiPredictorInterface
    {
        return new LlmPredictor();
    }

    public function createHeuristicDriver(): AiPredictorInterface
    {
        return new HeuristicPredictor();
    }
}