<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

trait HasDashboardRuntimeData
{
    /**
     * @return array<string, mixed>
     */
    protected function runtimeStatus(): array
    {
        $redisReachable = false;
        $redisError = null;

        try {
            $redisReachable = $this->isRedisPingSuccessful(Redis::ping());
        } catch (Throwable $exception) {
            $redisError = Str::limit($exception->getMessage(), 160);
        }

        return [
            'app_env' => Config::get('app.env'),
            'queue_connection' => Config::get('queue.default'),
            'cache_store' => Config::get('cache.default'),
            'redis_client' => Config::get('database.redis.client'),
            'redis_reachable' => $redisReachable,
            'redis_error' => $redisError,
            'jobs_pending' => DB::table('jobs')->count(),
            'jobs_failed' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function latestErrorHighlights(int $limit = 6): array
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

    protected function isRedisPingSuccessful(mixed $response): bool
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
