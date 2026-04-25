<div x-data="{ refreshing: false }" x-on:livewire-refresh.window="refreshing = true; $wire.refresh().then(() => refreshing = false)" class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Runtime Monitor</h2>
        <span x-show="refreshing" x-cloak class="text-xs text-blue-500">⟳</span>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Webhook Rate</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $webhookRate }}<span class="text-xs font-normal text-gray-400">/h</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Fusion Rate</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $fusionRate }}<span class="text-xs font-normal text-gray-400">/h</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Avg Latency</div>
            <div class="text-xl font-bold text-gray-900 dark:text-white">{{ $avgLatencyMs }}<span class="text-xs font-normal text-gray-400">ms</span></div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Trade Success</div>
            <div class="text-xl font-bold {{ $tradeSuccessRate >= 80 ? 'text-green-600' : ($tradeSuccessRate >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $tradeSuccessRate }}<span class="text-xs font-normal text-gray-400">%</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Queue</span>
            @if($jobsFailed > 0)
                <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">{{ $jobsFailed }} failed</span>
            @endif
        </div>
        <div class="flex gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span>Pending: <span class="font-mono font-semibold text-gray-700 dark:text-gray-200">{{ $jobsPending }}</span></span>
            <span>Failed: <span class="font-mono font-semibold text-red-600">{{ $jobsFailed }}</span></span>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Redis Status</span>
            @if($redisReachable)
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span class="text-xs text-green-600 dark:text-green-400">Connected</span>
            @else
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                <span class="text-xs text-red-600 dark:text-red-400">Unavailable</span>
            @endif
        </div>
        @if($redisError)
            <p class="text-xs text-red-500 dark:text-red-400 font-mono leading-tight">{{ $redisError }}</p>
        @endif
    </div>

    @if(! empty($errorHighlights))
        <div class="bg-red-50 dark:bg-red-900/10 rounded-xl p-3 border border-red-200 dark:border-red-800">
            <div class="text-xs font-semibold text-red-700 dark:text-red-300 mb-2">Recent Errors</div>
            <div class="space-y-1">
                @foreach($errorHighlights as $error)
                    <p class="text-xs font-mono text-red-600 dark:text-red-400 leading-tight">{{ $error }}</p>
                @endforeach
            </div>
        </div>
    @endif
</div>
