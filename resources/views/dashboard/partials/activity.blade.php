<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Recent Decisions & Executions</h2>
            <a href="{{ route('positions') }}" class="text-sm font-semibold text-brand-600 dark:text-brand-400">Lihat Positions</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Market</th>
                        <th class="px-4 py-3">Stage</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Message</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Waktu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($recentExecutions as $execution)
                        <tr>
                            <td class="px-4 py-4 font-mono font-semibold text-gray-800 dark:text-gray-100">{{ $execution->market_id ?? '-' }}</td>
                            <td class="px-4 py-4"><span class="font-mono text-xs">{{ $execution->stage }}</span></td>
                            <td class="px-4 py-4"><span class="font-mono text-xs">{{ $execution->action ?? '-' }}</span></td>
                            <td class="px-4 py-4 text-xs text-gray-600 dark:text-gray-300">{{ $execution->message ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <span class="badge {{ $execution->status === 'success' ? 'badge-success' : ($execution->status === 'warning' || $execution->status === 'error' ? 'badge-danger' : 'badge-info') }}">{{ $execution->status }}</span>
                            </td>
                            <td class="px-4 py-4 text-right text-gray-500 dark:text-gray-400">{{ ($execution->occurred_at ?? $execution->created_at)?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada execution tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Live Signals</h2>
            <a href="{{ route('signals') }}" class="text-sm font-semibold text-brand-600 dark:text-brand-400">Lihat Signals</a>
        </div>
        <div class="p-5 space-y-4">
            @forelse ($recentSignals as $signal)
                <div class="rounded-xl border border-gray-100 dark:border-dark-border p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-mono">{{ $signal->market_id }}</p>
                        <span class="badge {{ $signal->direction > 0 ? 'badge-success' : 'badge-danger' }}">{{ $signal->direction > 0 ? 'YES' : 'NO' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        Wallet: <span class="font-mono">{{ $signal->wallet?->address ?? 'unknown' }}</span>
                    </p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        Strength: <span class="font-mono">{{ number_format($signal->strength, 3) }}</span>
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $signal->created_at?->diffForHumans() }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada signal terbaru.</p>
            @endforelse
        </div>
    </div>
</div>
