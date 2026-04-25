<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">System Status</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">
            {{ $runtime['redis_reachable'] ? 'Active' : 'Degraded' }}
        </h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Queue: {{ $runtime['queue_connection'] }} | Cache: {{ $runtime['cache_store'] }}</p>
    </div>
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Tracked Wallets</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['tracked_wallets']) }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Wallet aktif untuk signal processing</p>
    </div>
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signals 1H</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['signals_1h']) }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Signal yang terbentuk satu jam terakhir</p>
    </div>
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Open Positions</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['open_positions']) }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Posisi status `open` di sistem</p>
    </div>
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Exposure</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">${{ number_format($stats['active_exposure'], 2) }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Akumulasi `size x entry_price` posisi open</p>
    </div>
    <div class="card p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Queue Health</p>
        <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['queue_backlog']) }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Pending: {{ number_format($stats['queue_backlog']) }} | Failed: {{ number_format($stats['failed_jobs']) }}</p>
    </div>
</div>
