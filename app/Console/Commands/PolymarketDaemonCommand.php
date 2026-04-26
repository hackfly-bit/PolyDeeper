<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use Illuminate\Console\Command;
use App\Jobs\ProcessWalletTradeJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
    public function handle(): int
    {
        $this->info('Starting Polymarket Daemon listener...');

        while (true) {
            $trades = $this->fetchRecentTrades();

            foreach ($trades as $trade) {
                Log::info('Detected trade from tracked wallet', ['wallet' => $trade['wallet']]);

                ProcessWalletTradeJob::dispatch($trade);
            }

            sleep(2);
        }

        return self::SUCCESS;
    }

    private function fetchRecentTrades(): array
    {
        $wallets = Wallet::query()->pluck('address')->filter()->values()->all();
        if (count($wallets) === 0) {
            return [];
        }

        $sinceTimestamp = (int) (Redis::get('polymarket:last_trade_timestamp') ?? (time() - 30));
        $tradeRows = [];

        try {
            $response = Http::baseUrl(rtrim((string) config('services.polymarket.data_host'), '/'))
                ->timeout((int) config('services.polymarket.timeout_seconds', 15))
                ->acceptJson()
                ->get('/trades', [
                    'maker_addresses' => implode(',', $wallets),
                    'start_ts' => $sinceTimestamp,
                    'limit' => 200,
                ]);

            $response->throw();
            $rows = $response->json();
            $rows = is_array($rows) ? $rows : [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $wallet = (string) ($row['maker_address'] ?? $row['wallet'] ?? '');
                if ($wallet === '') {
                    continue;
                }

                $timestamp = (int) ($row['timestamp'] ?? time());
                $tradeRows[] = [
                    'wallet' => $wallet,
                    'market_id' => (string) ($row['condition_id'] ?? $row['market_id'] ?? ''),
                    'condition_id' => (string) ($row['condition_id'] ?? ''),
                    'token_id' => $row['token_id'] ?? null,
                    'side' => strtoupper((string) ($row['side'] ?? 'YES')),
                    'price' => (float) ($row['price'] ?? 0),
                    'size' => (float) ($row['size'] ?? 0),
                    'tx_hash' => $row['transaction_hash'] ?? null,
                    'timestamp' => $timestamp,
                ];

                if ($timestamp > $sinceTimestamp) {
                    $sinceTimestamp = $timestamp;
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed polling Data API trades', [
                'message' => $exception->getMessage(),
            ]);
        }

        Redis::set('polymarket:last_trade_timestamp', $sinceTimestamp);

        return $tradeRows;
    }
}
