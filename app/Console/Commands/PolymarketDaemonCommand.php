<?php

namespace App\Console\Commands;

use App\Jobs\ProcessWalletTradeJob;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Psr\Log\LoggerInterface;
use Throwable;

class PolymarketDaemonCommand extends Command
{
    private const LISTENER_LOCK_KEY = 'polymarket:listener:lock';

    private const CURSOR_KEY = 'polymarket:last_trade_timestamp';

    private const LOG_CHANNEL = 'polymarket-listener';

    protected $signature = 'polymarket:listen
        {--once : Run a single polling cycle and exit}
        {--sleep=3 : Delay between polling cycles in seconds}
        {--limit=200 : Max trade rows fetched per request}
        {--lookback=30 : Initial lookback in seconds when cursor is empty}
        {--rewind=5 : Rewind cursor in seconds to reduce missed trades between loops}
        {--max-loops=0 : Stop after N loops, 0 keeps running}
        {--lock-seconds=30 : Distributed lock duration in seconds}
        {--stop-on-error : Stop listener when a polling error occurs}';

    protected $description = 'Polls Polymarket/Polygon RPC for trades from tracked wallets';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sleepSeconds = max(0, (int) $this->option('sleep'));
        $limit = max(1, (int) $this->option('limit'));
        $lookbackSeconds = max(1, (int) $this->option('lookback'));
        $rewindSeconds = max(0, (int) $this->option('rewind'));
        $lockSeconds = max(10, (int) $this->option('lock-seconds'));
        $maxLoops = max(0, (int) $this->option('max-loops'));
        $stopOnError = (bool) $this->option('stop-on-error');
        $runOnce = (bool) $this->option('once');

        if ($runOnce) {
            $maxLoops = 1;
        }

        $result = Cache::lock(self::LISTENER_LOCK_KEY, $lockSeconds)->get(function () use (
            $sleepSeconds,
            $limit,
            $lookbackSeconds,
            $rewindSeconds,
            $maxLoops,
            $stopOnError,
            $runOnce
        ) {
            return $this->runListener(
                sleepSeconds: $sleepSeconds,
                limit: $limit,
                lookbackSeconds: $lookbackSeconds,
                rewindSeconds: $rewindSeconds,
                maxLoops: $maxLoops,
                stopOnError: $stopOnError,
                runOnce: $runOnce,
            );
        });

        if ($result === false) {
            $this->warn('Listener tidak dijalankan karena instance lain masih memegang lock.');

            $this->listenerLogger()->warning('Polymarket listener start skipped because another instance is already running', [
                'lock_key' => self::LISTENER_LOCK_KEY,
                'lock_seconds' => $lockSeconds,
            ]);

            return self::FAILURE;
        }

        return $result;
    }

    private function runListener(
        int $sleepSeconds,
        int $limit,
        int $lookbackSeconds,
        int $rewindSeconds,
        int $maxLoops,
        bool $stopOnError,
        bool $runOnce
    ): int {
        $this->info(sprintf(
            'Polymarket listener dimulai. once=%s sleep=%d limit=%d lookback=%d rewind=%d max_loops=%d',
            $runOnce ? 'yes' : 'no',
            $sleepSeconds,
            $limit,
            $lookbackSeconds,
            $rewindSeconds,
            $maxLoops
        ));

        $this->listenerLogger()->info('Polymarket listener started', [
            'once' => $runOnce,
            'sleep_seconds' => $sleepSeconds,
            'limit' => $limit,
            'lookback_seconds' => $lookbackSeconds,
            'rewind_seconds' => $rewindSeconds,
            'max_loops' => $maxLoops,
            'stop_on_error' => $stopOnError,
        ]);

        $loop = 0;
        $consecutiveIdleLoops = 0;
        $consecutiveTruncatedLoops = 0;

        while ($maxLoops === 0 || $loop < $maxLoops) {
            $loop++;
            $summary = $this->fetchRecentTrades($limit, $lookbackSeconds, $rewindSeconds);
            $dispatched = 0;

            foreach ($summary['trades'] as $trade) {
                ProcessWalletTradeJob::dispatch($trade);
                $dispatched++;
            }

            $message = sprintf(
                'Listener loop #%d selesai. wallets=%d fetched=%d dispatched=%d cursor=%d',
                $loop,
                $summary['wallet_count'],
                $summary['fetched'],
                $dispatched,
                $summary['next_cursor']
            );

            if ($summary['truncated']) {
                $message .= ' truncated=yes';
            }

            if ($summary['error'] !== null) {
                $message .= ' error='.$summary['error'];
            }

            $isIdleLoop = $summary['wallet_count'] > 0 && $summary['error'] === null && $summary['fetched'] === 0;

            if ($isIdleLoop) {
                $consecutiveIdleLoops++;
            } else {
                $consecutiveIdleLoops = 0;
            }

            if ($summary['truncated']) {
                $consecutiveTruncatedLoops++;
            } else {
                $consecutiveTruncatedLoops = 0;
            }

            if ($summary['wallet_count'] === 0) {
                $this->warn('Listener idle: belum ada wallet aktif untuk dipoll.');
            } elseif ($summary['error'] !== null) {
                $this->warn($message);
            } elseif ($isIdleLoop) {
                if ($consecutiveIdleLoops === 1) {
                    $this->line('Tidak ada trade baru untuk wallet terpantau. Listener akan lanjut polling...');
                } elseif ($consecutiveIdleLoops % 10 === 0) {
                    $this->line(sprintf(
                        'Masih belum ada trade baru setelah %d loop (cursor=%d).',
                        $consecutiveIdleLoops,
                        $summary['next_cursor']
                    ));
                }
            } else {
                $this->line($message);
            }

            $this->listenerLogger()->info('Polymarket listener loop completed', [
                'loop' => $loop,
                'wallet_count' => $summary['wallet_count'],
                'requested_since' => $summary['requested_since'],
                'stored_cursor' => $summary['stored_cursor'],
                'next_cursor' => $summary['next_cursor'],
                'raw_rows' => $summary['raw_rows'],
                'fetched' => $summary['fetched'],
                'dispatched' => $dispatched,
                'truncated' => $summary['truncated'],
                'error' => $summary['error'],
            ]);

            if ($summary['truncated'] && ($consecutiveTruncatedLoops === 1 || $consecutiveTruncatedLoops % 10 === 0)) {
                $this->warn(sprintf(
                    'Respons trade menyentuh limit (%d) sebanyak %d loop berturut-turut. Pertimbangkan naikkan --limit atau kecilkan --sleep.',
                    $limit,
                    $consecutiveTruncatedLoops
                ));

                $this->listenerLogger()->warning('Polymarket listener response hit the configured limit; some trades may require another polling cycle', [
                    'loop' => $loop,
                    'consecutive_truncated_loops' => $consecutiveTruncatedLoops,
                    'limit' => $limit,
                    'raw_rows' => $summary['raw_rows'],
                    'fetched' => $summary['fetched'],
                    'requested_since' => $summary['requested_since'],
                    'next_cursor' => $summary['next_cursor'],
                ]);
            }

            if ($summary['error'] !== null && $stopOnError) {
                $this->error('Listener dihentikan karena terjadi error polling dan --stop-on-error aktif.');

                $this->listenerLogger()->error('Polymarket listener stopped because polling failed and stop-on-error is enabled', [
                    'loop' => $loop,
                    'error' => $summary['error'],
                ]);

                return self::FAILURE;
            }

            if ($maxLoops > 0 && $loop >= $maxLoops) {
                break;
            }

            if ($sleepSeconds > 0) {
                sleep($sleepSeconds);
            }
        }

        $this->info(sprintf('Polymarket listener berhenti setelah %d loop.', $loop));

        $this->listenerLogger()->info('Polymarket listener stopped', [
            'loops' => $loop,
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     wallet_count:int,
     *     requested_since:int,
     *     stored_cursor:int,
     *     next_cursor:int,
     *     raw_rows:int,
     *     fetched:int,
     *     truncated:bool,
     *     error:?string,
     *     trades:array<int, array<string, mixed>>
     * }
     */
    private function fetchRecentTrades(int $limit, int $lookbackSeconds, int $rewindSeconds): array
    {
        $wallets = Wallet::query()
            ->pluck('address')
            ->filter()
            ->unique()
            ->values();

        $storedCursor = (int) (Redis::get(self::CURSOR_KEY) ?? (time() - $lookbackSeconds));
        $requestedSince = max(0, $storedCursor - $rewindSeconds);

        if ($wallets->isEmpty()) {
            return [
                'wallet_count' => 0,
                'requested_since' => $requestedSince,
                'stored_cursor' => $storedCursor,
                'next_cursor' => $storedCursor,
                'raw_rows' => 0,
                'fetched' => 0,
                'truncated' => false,
                'error' => null,
                'trades' => [],
            ];
        }

        $tradeRows = [];
        $highestTimestamp = $storedCursor;
        $rawRows = 0;
        $truncated = false;
        $seenTradeKeys = [];

        try {
            foreach ($wallets as $trackedWallet) {
                $response = Http::baseUrl(rtrim((string) config('services.polymarket.data_host'), '/'))
                    ->timeout((int) config('services.polymarket.timeout_seconds', 15))
                    ->acceptJson()
                    ->withOptions([
                        'verify' => false,
                    ])
                    ->retry(2, 300)
                    ->get('/trades', [
                        'user' => $trackedWallet,
                        'start_ts' => $requestedSince,
                        'limit' => $limit,
                    ]);

                $response->throw();

                $rows = $this->normalizeTradeRows($response->json());
                $rowCount = count($rows);
                $rawRows += $rowCount;
                $truncated = $truncated || $rowCount >= $limit;

                foreach ($rows as $row) {
                    $wallet = (string) ($row['maker_address'] ?? $row['makerAddress'] ?? $row['wallet'] ?? $row['proxyWallet'] ?? $trackedWallet);
                    $timestamp = (int) ($row['timestamp'] ?? $requestedSince);
                    $conditionId = (string) ($row['condition_id'] ?? $row['conditionId'] ?? $row['market_id'] ?? $row['marketId'] ?? '');
                    $tokenId = $row['token_id'] ?? $row['tokenId'] ?? $row['asset'] ?? null;
                    $txHash = $row['transaction_hash'] ?? $row['transactionHash'] ?? null;

                    $tradeKey = strtolower((string) ($txHash ?? '')).'|'.$conditionId.'|'.strtolower($wallet).'|'.$timestamp;
                    if (isset($seenTradeKeys[$tradeKey])) {
                        continue;
                    }
                    $seenTradeKeys[$tradeKey] = true;

                    $tradeRows[] = [
                        'wallet' => $wallet,
                        'market_id' => $conditionId,
                        'condition_id' => $conditionId,
                        'token_id' => $tokenId,
                        'side' => strtoupper((string) ($row['side'] ?? 'YES')),
                        'price' => (float) ($row['price'] ?? 0),
                        'size' => (float) ($row['size'] ?? 0),
                        'tx_hash' => $txHash,
                        'timestamp' => $timestamp,
                    ];

                    if ($timestamp > $highestTimestamp) {
                        $highestTimestamp = $timestamp;
                    }
                }
            }

            usort($tradeRows, function (array $left, array $right): int {
                return $left['timestamp'] <=> $right['timestamp'];
            });
        } catch (Throwable $exception) {
            $this->listenerLogger()->warning('Polymarket listener failed polling Data API trades', [
                'message' => $exception->getMessage(),
                'requested_since' => $requestedSince,
                'limit' => $limit,
            ]);

            return [
                'wallet_count' => $wallets->count(),
                'requested_since' => $requestedSince,
                'stored_cursor' => $storedCursor,
                'next_cursor' => $storedCursor,
                'raw_rows' => 0,
                'fetched' => 0,
                'truncated' => false,
                'error' => $exception->getMessage(),
                'trades' => [],
            ];
        }

        Redis::set(self::CURSOR_KEY, $highestTimestamp);

        return [
            'wallet_count' => $wallets->count(),
            'requested_since' => $requestedSince,
            'stored_cursor' => $storedCursor,
            'next_cursor' => $highestTimestamp,
            'raw_rows' => $rawRows,
            'fetched' => count($tradeRows),
            'truncated' => $truncated,
            'error' => null,
            'trades' => $tradeRows,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTradeRows(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return collect($decoded)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->values()
                ->all();
        }

        $embedded = $decoded['data'] ?? $decoded['trades'] ?? null;

        if (is_array($embedded) && array_is_list($embedded)) {
            return collect($embedded)
                ->filter(fn (mixed $row): bool => is_array($row))
                ->values()
                ->all();
        }

        return [$decoded];
    }

    private function listenerLogger(): LoggerInterface
    {
        return Log::channel(self::LOG_CHANNEL);
    }
}
