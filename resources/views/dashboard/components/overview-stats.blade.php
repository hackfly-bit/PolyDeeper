<div
    x-data="{ refreshing: false }"
    x-on:dashboard-refresh.window="refreshing = true; Livewire.find('{{ $this->getId() }}').call('refresh').then(() => refreshing = false)"
    wire:poll.5s="refresh"
    class="space-y-4"
>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Overview Status</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Auto refresh aktif setiap 5 detik
                @if($lastRefreshedAt)
                    <span class="font-mono">| {{ $lastRefreshedAt }}</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('settings.polymarket.accounts.refresh-balances') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm font-semibold text-blue-700 transition hover:border-blue-300 hover:text-blue-800 dark:border-blue-900/50 dark:bg-dark-surface dark:text-blue-300 dark:hover:border-blue-700"
                >
                    Refresh Saldo Account
                </button>
            </form>
            <button
                type="button"
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-dark-border dark:bg-dark-surface dark:text-gray-200 dark:hover:border-brand-500/40 dark:hover:text-brand-300"
            >
                Reload Card
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">System Status</p>
                <span class="inline-flex items-center gap-2 rounded-full px-2 py-1 text-xs font-semibold {{ ($runtime['redis_reachable'] ?? false) ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' }}">
                    <span class="h-2.5 w-2.5 rounded-full {{ ($runtime['redis_reachable'] ?? false) ? 'bg-green-500 animate-pulse shadow-[0_0_10px_rgba(34,197,94,0.8)]' : 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.6)]' }}"></span>
                    {{ ($runtime['redis_reachable'] ?? false) ? 'Active' : 'Degraded' }}
                </span>
            </div>
            <h3 class="mt-3 text-2xl font-extrabold text-gray-900 dark:text-white">
                {{ ($runtime['redis_reachable'] ?? false) ? 'Semua aktif' : 'Perlu perhatian' }}
            </h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Queue: {{ $runtime['queue_connection'] ?? '-' }} | Cache: {{ $runtime['cache_store'] ?? '-' }}
            </p>
            @if(! empty($runtime['redis_error']))
                <p class="mt-2 text-xs font-mono text-red-600 dark:text-red-300">{{ $runtime['redis_error'] }}</p>
            @endif
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Tracked Wallets</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['tracked_wallets'] ?? 0) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Wallet aktif untuk signal processing</p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signals 1H</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['signals_1h'] ?? 0) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Signal yang terbentuk satu jam terakhir</p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Open Positions</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['open_positions'] ?? 0) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Posisi dengan status open di sistem</p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Active Exposure</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">${{ number_format((float) ($stats['active_exposure'] ?? 0), 2) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Akumulasi size x entry_price posisi open</p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Queue Health</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">{{ number_format($stats['queue_backlog'] ?? 0) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Pending: {{ number_format($stats['queue_backlog'] ?? 0) }} | Failed: {{ number_format($stats['failed_jobs'] ?? 0) }}
            </p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Stored Balance (USD)</p>
            <h3 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white font-mono">${{ number_format((float) ($stats['stored_balance_total_usd'] ?? 0), 2) }}</h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Total saldo tersimpan account aktif: {{ number_format((int) ($stats['active_account_count'] ?? 0)) }} account</p>
        </div>

        <div class="card p-5">
            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Balance Last Refresh</p>
            <h3 class="mt-2 text-lg font-extrabold text-gray-900 dark:text-white font-mono">
                {{ !empty($stats['stored_balance_latest_refresh']) ? \Illuminate\Support\Carbon::parse($stats['stored_balance_latest_refresh'])->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') : '-' }}
            </h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Zona waktu: WIB (Asia/Jakarta)</p>
        </div>
    </div>
</div>
