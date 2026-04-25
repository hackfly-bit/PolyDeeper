<?php

namespace App\Livewire\Dashboard;

use App\Models\ExecutionLog;
use App\Models\Position;
use App\Models\Signal;
use Illuminate\View\View;
use Livewire\Component;

class LiveActivity extends Component
{
    public array $recentSignals = [];

    public array $recentExecutions = [];

    public int $openPositions = 0;

    public int $totalExposure = 0;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->recentSignals = Signal::query()
            ->with('wallet:id,address,weight')
            ->latest()
            ->limit(6)
            ->get()
            ->toArray();

        $this->recentExecutions = ExecutionLog::query()
            ->latest()
            ->limit(6)
            ->get()
            ->toArray();

        $openPositionsQuery = Position::query()->where('status', 'open');
        $this->openPositions = $openPositionsQuery->count();
        $this->totalExposure = (float) (clone $openPositionsQuery)
            ->selectRaw('COALESCE(SUM(size * entry_price), 0) as total_exposure')
            ->value('total_exposure');
    }

    public function render(): View
    {
        return view('dashboard.components.live-activity');
    }
}
