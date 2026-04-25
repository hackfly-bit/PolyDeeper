<div x-data="{ refreshing: false }" x-on:livewire-refresh.window="refreshing = true; $wire.refresh().then(() => refreshing = false)" class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Signals & Executions</h2>
        <span x-show="refreshing" x-cloak class="text-xs text-blue-500">⟳</span>
    </div>

    @if(empty($recentSignals) && empty($recentExecutions))
        <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
            No recent activity. Waiting for signals...
        </div>
    @else
        <div class="space-y-2">
            @foreach($recentSignals as $signal)
                <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $signal['market_id'] ?? '—' }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded {{ ($signal['direction'] ?? 0) > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' }}">
                            {{ ($signal['direction'] ?? 0) > 0 ? '▲' : '▼' }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ isset($signal['created_at']) ? \Carbon\Carbon::parse($signal['created_at'])->diffForHumans() : '' }}
                    </div>
                </div>
            @endforeach

            @foreach($recentExecutions as $execution)
                <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-700 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-mono font-semibold text-gray-700 dark:text-gray-200">{{ $execution['market_id'] ?? '—' }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $execution['action'] ?? '—' }}</span>
                    </div>
                    <span class="text-xs px-1.5 py-0.5 rounded
                        {{ ($execution['status'] ?? '') === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' }}">
                        {{ $execution['status'] ?? '—' }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-cols-2 gap-3 pt-2">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2 text-center">
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $openPositions }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Open Positions</div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-lg px-3 py-2 text-center">
            <div class="text-xl font-bold text-gray-900 dark:text-white">${{ number_format($totalExposure, 2) }}</div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Total Exposure</div>
        </div>
    </div>
</div>
