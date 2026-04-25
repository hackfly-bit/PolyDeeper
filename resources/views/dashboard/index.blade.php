@extends('layouts.app')

@section('content')
<div
    x-data="{}"
    x-init="setInterval(() => window.dispatchEvent(new CustomEvent('livewire-refresh')), 5000)"
    class="max-w-7xl mx-auto space-y-6 animate-fade-in-up"
>
    @include('dashboard.partials.stats')
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
            @include('dashboard.partials.wallet-performance')
        </div>
    </div>
</div>
@endsection
