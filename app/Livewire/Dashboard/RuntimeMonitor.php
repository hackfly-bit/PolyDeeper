<?php

namespace App\Livewire\Dashboard;

use App\Models\ExecutionLog;
use App\Models\WalletTrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class RuntimeMonitor extends Component
{
    public bool $redisReachable = false;

    public ?string $redisError = null;

    public int $jobsPending = 0;

    public int $jobsFailed = 0;

    public int $webhookRate = 0;

    public int $fusionRate = 0;

    public int $avgLatencyMs = 0;

    public int $tradeSuccessRate = 0;

    public array $errorHighlights = [];

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->redisReachable = false;
        $this->redisError = null;

        try {
            $this->redisReachable = $this->isRedisPingSuccessful(Redis::ping());
        } catch (\Throwable $exception) {
            $this->redisError = Str::limit($exception->getMessage(), 160);
        }

        $this->jobsPending = DB::table('jobs')->count();
        $this->jobsFailed = DB::table('failed_jobs')->count();

        $now = now();
        $oneHourAgo = $now->copy()->subHour();
        $today = $now->toDateString();

        $this->webhookRate = WalletTrade::query()
            ->where('traded_at', '>=', $oneHourAgo)
            ->count();

        $this->fusionRate = ExecutionLog::query()
            ->where('stage', 'fusion')
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        $latencyAvg = ExecutionLog::query()
            ->whereNotNull('execution_time_ms')
            ->where('created_at', '>=', $oneHourAgo)
            ->selectRaw('AVG(execution_time_ms) as avg_latency')
            ->value('avg_latency');

        $this->avgLatencyMs = $latencyAvg ? (int) $latencyAvg : 0;

        $totalExecutions = ExecutionLog::query()
            ->where('stage', 'execution')
            ->whereDate('created_at', $today)
            ->count();

        $successfulExecutions = ExecutionLog::query()
            ->where('stage', 'execution')
            ->where('trade_executed', true)
            ->whereDate('created_at', $today)
            ->count();

        $this->tradeSuccessRate = $totalExecutions > 0
            ? (int) round(($successfulExecutions / $totalExecutions) * 100)
            : 0;

        $this->errorHighlights = $this->readErrorLog();
    }

    private function readErrorLog(int $limit = 6): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return [];
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $highlights = [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim($lines[$index]);

            if ($line === '') {
                continue;
            }

            if (! Str::contains($line, ['.ERROR:', 'exception', 'Connection refused'])) {
                continue;
            }

            $highlights[] = Str::limit($line, 220);

            if (count($highlights) >= $limit) {
                break;
            }
        }

        return $highlights;
    }

    public function render(): View
    {
        return view('dashboard.components.runtime-monitor');
    }

    private function isRedisPingSuccessful(mixed $response): bool
    {
        if ($response === true) {
            return true;
        }

        if (is_object($response) && method_exists($response, 'getPayload')) {
            /** @var mixed $payload */
            $payload = $response->getPayload();
            $response = $payload;
        }

        if (! is_scalar($response)) {
            return false;
        }

        return strtoupper(ltrim((string) $response, '+')) === 'PONG';
    }
}
