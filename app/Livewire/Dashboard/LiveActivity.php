<?php

namespace App\Livewire\Dashboard;

use App\Models\ExecutionLog;
use App\Models\Market;
use App\Models\Position;
use App\Models\Signal;
use Illuminate\View\View;
use Livewire\Component;

class LiveActivity extends Component
{
    public array $recentSignals = [];

    public array $recentExecutions = [];

    public array $marketTitlesByCondition = [];

    public int $openPositions = 0;

    public int $totalExposure = 0;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $signalRows = Signal::query()
            ->with([
                'wallet:id,name,address,weight',
                'market:id,condition_id,question,title',
            ])
            ->latest()
            ->limit(6)
            ->get();

        $executionRows = ExecutionLog::query()
            ->latest()
            ->limit(6)
            ->get();

        $this->recentSignals = $signalRows->toArray();
        $this->recentExecutions = $executionRows->toArray();

        $marketConditionIds = collect()
            ->merge($signalRows->pluck('market_id')->filter()->values())
            ->merge($signalRows->pluck('condition_id')->filter()->values())
            ->merge($executionRows->pluck('market_id')->filter()->values())
            ->unique()
            ->values();

        $this->marketTitlesByCondition = Market::query()
            ->whereIn('condition_id', $marketConditionIds)
            ->get(['condition_id', 'question', 'title'])
            ->mapWithKeys(function (Market $market): array {
                return [
                    $market->condition_id => trim((string) ($market->question ?? $market->title ?? '')),
                ];
            })
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
