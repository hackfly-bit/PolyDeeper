<div
    x-data="{ refreshing: false }"
    x-on:dashboard-refresh.window="refreshing = true; Livewire.find('{{ $this->getId() }}').call('refresh').then(() => refreshing = false)"
    wire:poll.5s="refresh"
    class="card overflow-hidden"
>
    <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-dark-border sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tracked Wallet Performance</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Auto refresh aktif setiap 5 detik
                @if($lastRefreshedAt)
                    <span class="font-mono">| {{ $lastRefreshedAt }}</span>
                @endif
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-dark-border dark:bg-dark-surface dark:text-gray-200 dark:hover:border-brand-500/40 dark:hover:text-brand-300"
            >
                Reload Table
            </button>
            <a href="{{ route('wallets') }}" class="text-sm font-semibold text-brand-600 dark:text-brand-100">Kelola Wallet</a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50/70 text-xs uppercase tracking-wider text-gray-500 dark:bg-dark-bg/40">
                <tr>
                    <th class="px-4 py-3">Address</th>
                    <th class="px-4 py-3">Weight</th>
                    <th class="px-4 py-3">Win Rate</th>
                    <th class="px-4 py-3">ROI</th>
                    <th class="px-4 py-3 text-right">Last Active</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                @forelse ($walletPerformance as $wallet)
                    <tr>
                        <td class="px-4 py-4 font-mono text-xs text-gray-800 dark:text-gray-100">{{ $wallet['address'] }}</td>
                        <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet['weight'], 2) }}</td>
                        <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet['win_rate'], 2) }}</td>
                        <td class="px-4 py-4 font-mono {{ (float) $wallet['roi'] >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ number_format((float) $wallet['roi'], 2) }}
                        </td>
                        <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">
                            {{ $wallet['last_active'] ? \Illuminate\Support\Carbon::parse($wallet['last_active'])->diffForHumans() : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada wallet terdaftar.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
