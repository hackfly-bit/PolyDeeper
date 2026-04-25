<?php

namespace App\Livewire\Dashboard;

use App\Models\ExecutionLog;
use App\Models\Signal;
use App\Models\Wallet;
use App\Models\WalletTrade;
use Illuminate\View\View;
use Livewire\Component;

class PipelineStatus extends Component
{
    public array $pipeline = [];

    public int $trackedWallets = 0;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $now = now();
        $today = $now->toDateString();
        $oneHourAgo = $now->copy()->subHour();

        $this->trackedWallets = Wallet::count();

        $tradesToday = WalletTrade::query()
            ->whereDate('traded_at', $today)
            ->count();

        $signalsOneHour = Signal::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->count();

        $executionsToday = ExecutionLog::query()
            ->where('stage', 'execution')
            ->whereDate('created_at', $today)
            ->count();

        $riskCheckedToday = ExecutionLog::query()
            ->where('stage', 'risk')
            ->whereDate('created_at', $today)
            ->count();

        $fusionProcessedToday = ExecutionLog::query()
            ->where('stage', 'fusion')
            ->whereDate('created_at', $today)
            ->count();

        $this->pipeline = [
            'webhook' => $tradesToday,
            'trade' => $tradesToday,
            'signal' => $signalsOneHour,
            'fusion' => $fusionProcessedToday,
            'risk' => $riskCheckedToday,
            'execution' => $executionsToday,
        ];
    }

    public function render(): View
    {
        return view('dashboard.components.pipeline-status');
    }
}
