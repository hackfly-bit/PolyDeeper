<?php

namespace App\Http\Controllers;

use App\Models\ExecutionLog;
use App\Models\Position;
use App\Models\Signal;
use App\Models\Wallet;
use App\Models\WalletTrade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $oneHourAgo = $now->copy()->subHour();

        $trackedWallets = Wallet::count();
        $tradesToday = WalletTrade::query()
            ->whereDate('traded_at', $today)
            ->count();
        $signalsOneHour = Signal::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->count();
        $openPositionsQuery = Position::query()->where('status', 'open');
        $openPositionsCount = (clone $openPositionsQuery)->count();
        $activeExposure = (clone $openPositionsQuery)
            ->selectRaw('COALESCE(SUM(size * entry_price), 0) as total_exposure')
            ->value('total_exposure');
        $queueBacklog = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $logsToday = ExecutionLog::query()
            ->whereDate('occurred_at', $today);

        $recentSignals = Signal::query()
            ->with('wallet:id,address,weight')
            ->latest()
            ->limit(8)
            ->get();

        $recentExecutions = ExecutionLog::query()
            ->whereIn('stage', [
                'fusion_decision',
                'risk_rejected',
                'risk_passed',
                'trade_execution_started',
                'trade_executed',
                'trade_execution_failed',
            ])
            ->latest()
            ->limit(8)
            ->get();

        $walletPerformance = Wallet::query()
            ->latest('last_active')
            ->limit(10)
            ->get();

        $pipeline = [
            'webhook' => (clone $logsToday)->where('stage', 'webhook_received')->count(),
            'trade' => (clone $logsToday)->where('stage', 'trade_saved')->count(),
            'signal' => (clone $logsToday)->where('stage', 'signal_normalized')->count(),
            'fusion' => (clone $logsToday)->where('stage', 'fusion_decision')->count(),
            'risk' => (clone $logsToday)->where('stage', 'risk_passed')->count(),
            'execution' => (clone $logsToday)->where('stage', 'trade_executed')->count(),
        ];

        $riskAlerts = ExecutionLog::query()
            ->where('stage', 'risk_rejected')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'tracked_wallets' => $trackedWallets,
                'trades_today' => $tradesToday,
                'signals_1h' => $signalsOneHour,
                'open_positions' => $openPositionsCount,
                'active_exposure' => (float) $activeExposure,
                'queue_backlog' => $queueBacklog,
                'failed_jobs' => $failedJobs,
            ],
            'pipeline' => $pipeline,
            'recentSignals' => $recentSignals,
            'recentExecutions' => $recentExecutions,
            'walletPerformance' => $walletPerformance,
            'runtime' => $this->runtimeStatus(),
            'errorHighlights' => $this->latestErrorHighlights(),
            'riskAlerts' => $riskAlerts,
        ]);
    }

    public function positions(): View
    {
        return view('dashboard.positions', [
            'pageTitle' => 'Positions',
            'positions' => Position::query()->latest()->paginate(15),
        ]);
    }

    public function signals(): View
    {
        return view('dashboard.signals', [
            'pageTitle' => 'Signals',
            'signals' => Signal::query()
                ->with('wallet:id,address,weight')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function wallets(): View
    {
        return view('dashboard.wallets', [
            'pageTitle' => 'Tracked Wallets',
            'wallets' => Wallet::query()->latest('last_active')->paginate(20),
        ]);
    }

    public function storeWallet(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255', 'unique:wallets,address'],
            'weight' => ['required', 'numeric', 'min:0'],
            'win_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi' => ['required', 'numeric'],
            'last_active' => ['nullable', 'date'],
        ]);

        Wallet::query()->create($validated);

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil ditambahkan.');
    }

    public function updateWallet(Request $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validate([
            'address' => ['required', 'string', 'max:255', 'unique:wallets,address,'.$wallet->id],
            'weight' => ['required', 'numeric', 'min:0'],
            'win_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'roi' => ['required', 'numeric'],
            'last_active' => ['nullable', 'date'],
        ]);

        $wallet->update($validated);

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil diperbarui.');
    }

    public function destroyWallet(Wallet $wallet): RedirectResponse
    {
        $wallet->delete();

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil dihapus.');
    }

    public function settings(): View
    {
        return view('dashboard.settings', [
            'pageTitle' => 'System Settings',
            'runtime' => $this->runtimeStatus(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeStatus(): array
    {
        $redisReachable = false;
        $redisError = null;

        try {
            $redisReachable = Redis::ping() === 'PONG';
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
    private function latestErrorHighlights(int $limit = 6): array
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
}
