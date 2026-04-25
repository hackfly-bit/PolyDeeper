<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessWalletTradeJob;
use Illuminate\Support\Facades\Log;

class PolymarketDaemonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polymarket:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Polls Polymarket/Polygon RPC for trades from tracked wallets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starting Polymarket Daemon listener...");

        while (true) {
            // Mocking a fetch to Polygon RPC / Polymarket API
            // In real scenario, we use Web3/Guzzle to fetch latest block events for CTF contracts
            $trades = $this->fetchRecentTrades();

            foreach ($trades as $trade) {
                Log::info("Detected trade from tracked wallet", ['wallet' => $trade['wallet']]);

                // Dispatch to Queue
                ProcessWalletTradeJob::dispatch($trade);
            }

            // Sleep to avoid rate limits (e.g. 2 seconds)
            sleep(2);
        }
    }

    private function fetchRecentTrades(): array
    {
        // Mock response
        if (rand(1, 10) > 8) {
            return [
                [
                    'wallet' => '0xABC' . rand(100, 999),
                    'market_id' => 'TRUMP_2028',
                    'side' => rand(0, 1) ? 'YES' : 'NO',
                    'price' => rand(10, 90) / 100,
                    'size' => rand(100, 1000),
                    'timestamp' => time(),
                ]
            ];
        }

        return [];
    }
}