<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\AiPrediction\AiPredictionManager;
use App\Services\AiPrediction\AiPredictorInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiPredictionManager::class, function ($app) {
            return new AiPredictionManager($app);
        });

        $this->app->bind(AiPredictorInterface::class, function ($app) {
            return $app->make(AiPredictionManager::class)->driver();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
