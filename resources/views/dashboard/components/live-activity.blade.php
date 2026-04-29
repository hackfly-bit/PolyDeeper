<div
    x-data="{ refreshing: false }"
    x-on:dashboard-refresh.window="refreshing = true; Livewire.find('{{ $this->getId() }}').call('refresh').then(() => refreshing = false)"
    wire:poll.5s="refresh"
    class="space-y-4"
>
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Aktivitas Terkini</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Ringkas, mudah dibaca, refresh otomatis setiap 5 detik.</p>
        </div>
        <div class="flex items-center gap-2">
            <span x-show="refreshing" x-cloak class="text-xs text-blue-500">Refreshing...</span>
            <a
                href="{{ route('history') }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 dark:border-dark-border dark:bg-dark-surface dark:text-gray-200 dark:hover:border-brand-500/40 dark:hover:text-brand-300"
            >
                Lihat History
            </a>
            <button
                type="button"
                wire:click="refresh"
                wire:loading.attr="disabled"
                class="inline-flex items-center justify-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:border-brand-300 hover:text-brand-600 disabled:cursor-not-allowed disabled:opacity-60 dark:border-dark-border dark:bg-dark-surface dark:text-gray-200 dark:hover:border-brand-500/40 dark:hover:text-brand-300"
            >
                Reload
            </button>
        </div>
    </div>

    @if(empty($recentSignals) && empty($recentExecutions))
        <div class="text-center py-8 text-gray-400 dark:text-gray-500 text-sm">
            Belum ada aktivitas terbaru.
        </div>
    @else
        <div class="space-y-4">
            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Signal Terbaru</h3>
                <div class="space-y-2">
                    @forelse($recentSignals as $signal)
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    @php
                                        $signalMarketId = (string) ($signal['market_id'] ?? $signal['condition_id'] ?? '');
                                        $signalMarketTitle = trim((string) (($signal['market']['question'] ?? null)
                                            ?? ($signal['market']['title'] ?? null)
                                            ?? ($marketTitlesByCondition[$signalMarketId] ?? '')));
                                        $signalWalletName = trim((string) ($signal['wallet']['name'] ?? ''));
                                    @endphp
                                    <p class="truncate font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $signalMarketTitle !== '' ? $signalMarketTitle : ($signalMarketId !== '' ? $signalMarketId : '-') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $signalWalletName !== '' ? $signalWalletName : 'Wallet tanpa nama' }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded {{ ($signal['direction'] ?? 0) > 0 ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' }}">
                                    {{ ($signal['direction'] ?? 0) > 0 ? 'BUY' : 'SELL' }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                                {{ isset($signal['created_at']) ? \Carbon\Carbon::parse($signal['created_at'])->diffForHumans() : '-' }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada signal terbaru.
                        </div>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Execution Terbaru</h3>
                <div class="space-y-2">
                    @forelse($recentExecutions as $execution)
                        <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    @php
                                        $executionMarketId = (string) ($execution['market_id'] ?? '');
                                        $executionMarketTitle = trim((string) ($marketTitlesByCondition[$executionMarketId] ?? ''));
                                    @endphp
                                    <p class="truncate font-semibold text-gray-900 dark:text-gray-100">
                                        {{ ($execution['action'] ?? '-') }} - {{ $executionMarketTitle !== '' ? $executionMarketTitle : ($executionMarketId !== '' ? $executionMarketId : '-') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ ($execution['stage'] ?? '-') }} | {{ ($execution['message'] ?? '-') }}
                                    </p>
                                </div>
                                <span class="text-xs px-2 py-0.5 rounded {{ ($execution['status'] ?? '') === 'success' ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' }}">
                                    {{ strtoupper((string) ($execution['status'] ?? '-')) }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            Belum ada execution terbaru.
                        </div>
                    @endforelse
                </div>
            </div>
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
