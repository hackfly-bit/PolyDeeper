<?php

namespace App\Livewire\Dashboard;

use App\Models\Wallet;
use Illuminate\View\View;
use Livewire\Component;

class WalletPerformanceTable extends Component
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $walletPerformance = [];

    public ?string $lastRefreshedAt = null;

    public function mount(): void
    {
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->walletPerformance = Wallet::query()
            ->latest('last_active')
            ->limit(10)
            ->get()
            ->map(fn (Wallet $wallet): array => [
                'address' => $wallet->address,
                'weight' => (float) $wallet->weight,
                'win_rate' => (float) $wallet->win_rate,
                'roi' => (float) $wallet->roi,
                'last_active' => $wallet->last_active?->toIso8601String(),
            ])
            ->all();

        $this->lastRefreshedAt = now()->format('H:i:s');
    }

    public function render(): View
    {
        return view('dashboard.components.wallet-performance-table');
    }
}
