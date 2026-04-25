<div class="card overflow-hidden">
    <div class="p-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tracked Wallet Performance</h2>
        <a href="{{ route('wallets') }}" class="text-sm font-semibold text-brand-600 dark:text-brand-100">Kelola Wallet</a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
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
                        <td class="px-4 py-4 font-mono text-xs text-gray-800 dark:text-gray-100">{{ $wallet->address }}</td>
                        <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->weight, 2) }}</td>
                        <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->win_rate, 2) }}</td>
                        <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->roi, 2) }}</td>
                        <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">{{ $wallet->last_active?->diffForHumans() ?? '-' }}</td>
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
