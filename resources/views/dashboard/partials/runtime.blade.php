<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Runtime & Risk Alerts</h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Redis Client</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['redis_client'] ?? 'n/a' }}</p>
                <p class="mt-1 {{ $runtime['redis_reachable'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $runtime['redis_reachable'] ? 'Redis reachable' : 'Redis unavailable' }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Queue Worker</p>
                <p class="mt-2 text-gray-900 dark:text-white">Pending: <span class="font-mono">{{ number_format($runtime['jobs_pending']) }}</span></p>
                <p class="mt-1 text-gray-900 dark:text-white">Failed: <span class="font-mono">{{ number_format($runtime['jobs_failed']) }}</span></p>
            </div>
        </div>

        @if (!empty($runtime['redis_error']))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-500/10 dark:border-red-500/20 p-4">
                <p class="text-xs uppercase tracking-wider text-red-600 dark:text-red-400">Redis Error</p>
                <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $runtime['redis_error'] }}</p>
            </div>
        @endif

        <div class="mt-4">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Risk Rejected (Terbaru)</h3>
            <div class="mt-2 space-y-2">
                @forelse ($riskAlerts as $riskAlert)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/20 p-3">
                        <p class="text-xs text-amber-800 dark:text-amber-300 font-mono">{{ $riskAlert->market_id ?? '-' }} | {{ $riskAlert->action ?? 'SKIP' }}</p>
                        <p class="text-xs text-amber-700 dark:text-amber-200 mt-1">{{ $riskAlert->message }}</p>
                        <p class="text-[11px] text-amber-600 dark:text-amber-300 mt-1">{{ ($riskAlert->occurred_at ?? $riskAlert->created_at)?->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada risk rejection terbaru.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Recent Errors</h2>
        <div class="mt-4 space-y-3">
            @forelse ($errorHighlights as $errorLine)
                <div class="rounded-lg border border-red-200 dark:border-red-500/30 p-3 bg-red-50/60 dark:bg-red-500/10">
                    <p class="text-xs text-red-700 dark:text-red-300 break-words">{{ $errorLine }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada error terbaru yang terdeteksi.</p>
            @endforelse
        </div>
    </div>
</div>
