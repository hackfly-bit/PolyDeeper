@extends('layouts.app')

@section('content')
<div
    x-data="{
        refreshAll() {
            window.dispatchEvent(new CustomEvent('dashboard-refresh'));
        }
    }"
    class="max-w-7xl mx-auto space-y-6 animate-fade-in-up"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Dashboard Aktif</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Semua widget berjalan dengan auto refresh 5 detik dan bisa di-reload manual.</p>
        </div>
        <button
            type="button"
            @click="refreshAll()"
            class="inline-flex items-center justify-center rounded-xl border border-brand-200 bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700 dark:border-brand-500/30"
        >
            Reload Dashboard
        </button>
    </div>

    @if (session('dashboard_success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/30 dark:bg-green-900/20 dark:text-green-300">
            {{ session('dashboard_success') }}
        </div>
    @endif

    @livewire(\App\Livewire\Dashboard\OverviewStats::class, [], key('overview-stats'))

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2">
            @livewire(\App\Livewire\Dashboard\PipelineStatus::class, [], key('pipeline-status'))
        </div>
        <div>
            @livewire(\App\Livewire\Dashboard\RuntimeMonitor::class, [], key('runtime-monitor'))
        </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div>
            @livewire(\App\Livewire\Dashboard\LiveActivity::class, [], key('live-activity'))
        </div>
        <div>
            @livewire(\App\Livewire\Dashboard\WalletPerformanceTable::class, [], key('wallet-performance-table'))
        </div>
    </div>
</div>
@endsection
