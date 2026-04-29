<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasDashboardRuntimeData;
use App\Models\ExecutionLog;
use App\Models\Position;
use App\Models\Signal;
use App\Models\Wallet;
use App\Models\WalletTrade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use HasDashboardRuntimeData;

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
            ->with([
                'wallet:id,name,address,weight',
                'market:id,condition_id,question,title',
            ])
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
}
