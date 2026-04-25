<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Services\Backtesting\BacktestEngine;
use App\Services\SignalNormalizerService;
use App\Services\SignalAggregatorService;
use App\Services\FusionEngineService;
use App\Services\AiPrediction\AiPredictionManager;

class RunBacktestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polymarket:backtest {--file=storage/app/backtest_data.json} {--ai=heuristic}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Runs a historical backtest for the Polymarket bot algorithm';

    /**
     * Execute the console command.
     */
    public function handle(
        SignalNormalizerService $normalizer,
        SignalAggregatorService $aggregator,
        FusionEngineService $fusionEngine,
        AiPredictionManager $aiManager
    ) {
        $filePath = base_path($this->option('file'));

        if (!File::exists($filePath)) {
            $this->error("Backtest data file not found at: {$filePath}");
            return 1;
        }

        $this->info("Loading backtest data from {$filePath}...");
        $historicalTrades = json_decode(File::get($filePath), true);

        if (!$historicalTrades) {
            $this->error("Invalid JSON format in backtest file.");
            return 1;
        }

        // Initialize AI Predictor Strategy based on option
        $aiDriver = $this->option('ai'); // 'heuristic' or 'llm'
        $aiPredictor = $aiManager->driver($aiDriver);

        // Initialize Backtest Engine
        $engine = new BacktestEngine(
            $normalizer,
            $aggregator,
            $fusionEngine,
            $aiPredictor
        );

        $this->info("Starting backtest with '{$aiDriver}' AI Strategy...");

        // Run the engine
        $results = $engine->run($historicalTrades);

        // Print Results
        $this->newLine();
        $this->info("=== Backtest Results ===");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Initial Balance', '$' . number_format($results['initial_balance'], 2)],
                ['Final Balance', '$' . number_format($results['final_balance'], 2)],
                ['Total Historical Trades Processed', $results['historical_trades_processed']],
                ['Total Bot Executions (Virtual)', $results['total_trades_executed']],
                ['Open Positions Count', $results['open_positions_count']],
            ]
        );

        $this->newLine();
        if ($results['total_trades_executed'] > 0) {
            $this->info("Trade History:");
            $this->table(
                ['Market', 'Side', 'Entry Price', 'Size', 'Final Score', 'Wallet Signal', 'AI Signal'],
                collect($results['trade_history'])->map(function ($trade) {
                    return [
                        $trade['market_id'],
                        $trade['side'],
                        number_format($trade['entry_price'], 2),
                        number_format($trade['size'], 2),
                        number_format($trade['final_score'], 2),
                        number_format($trade['wallet_signal'], 2),
                        number_format($trade['ai_signal'], 2),
                    ];
                })->toArray()
            );
        }

        return 0;
    }
}