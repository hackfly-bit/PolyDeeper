<?php

namespace App\Livewire\Dashboard;

use App\Models\Position;
use App\Models\Signal;
use App\Models\Wallet;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class OverviewStats extends Component
{
    public array $stats = [];

    public array $runtime = [];

    public ?string $lastRefreshedAt = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $now = now();
        $oneHourAgo = $now->copy()->subHour();
        $openPositionsQuery = Position::query()->where('status', 'open');

        $this->stats = [
            'tracked_wallets' => Wallet::count(),
            'signals_1h' => Signal::query()
                ->where('created_at', '>=', $oneHourAgo)
                ->count(),
            'open_positions' => (clone $openPositionsQuery)->count(),
            'active_exposure' => (float) (clone $openPositionsQuery)
                ->selectRaw('COALESCE(SUM(size * entry_price), 0) as total_exposure')
                ->value('total_exposure'),
            'queue_backlog' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];

        $this->runtime = $this->runtimeStatus();
        $this->lastRefreshedAt = $now->format('H:i:s');
    }

    public function render(): View
    {
        return view('dashboard.components.overview-stats');
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeStatus(): array
    {
        $redisReachable = false;
        $redisError = null;

        try {
            $redisReachable = $this->isRedisPingSuccessful(Redis::ping());
        } catch (Throwable $exception) {
            $redisError = Str::limit($exception->getMessage(), 160);
        }

        return [
            'queue_connection' => (string) Config::get('queue.default'),
            'cache_store' => (string) Config::get('cache.default'),
            'redis_reachable' => $redisReachable,
            'redis_error' => $redisError,
        ];
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
