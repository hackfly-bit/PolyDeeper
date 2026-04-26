<div
    x-data="{ refreshing: false }"
    x-on:dashboard-refresh.window="refreshing = true; Livewire.find('{{ $this->getId() }}').call('refresh').then(() => refreshing = false)"
    wire:poll.5s="refresh"
    class="space-y-4"
>
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pipeline Flow</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">Status pipeline aktif dan refresh otomatis tiap 5 detik</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500 dark:text-gray-400">
                <span x-show="refreshing" x-cloak>⟳</span>
                Polling: 5s
            </span>
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

    <div class="grid grid-cols-6 gap-2">
        @foreach([
            'webhook' => ['Webhook', 'Ingesti data trade dari webhook'],
            'trade' => ['Trade', 'Wallet trade tersimpan'],
            'signal' => ['Signal', 'Signal wallet terbentuk'],
            'fusion' => ['Fusion', 'AI scoring & fusion decision'],
            'risk' => ['Risk', 'Risk guard validation'],
            'execution' => ['Execution', 'Trade tereksekusi'],
        ] as $key => [$label, $desc])
            <div class="rounded-xl border border-gray-200 bg-white p-3 transition-shadow hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $label }}</span>
                    <span class="h-2.5 w-2.5 rounded-full {{ ($pipeline[$key] ?? 0) > 0 ? 'bg-green-500 animate-pulse shadow-[0_0_10px_rgba(34,197,94,0.8)]' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    {{ $pipeline[$key] ?? 0 }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-tight">{{ $desc }}</div>
            </div>
        @endforeach
    </div>

    <div class="text-xs text-gray-400 dark:text-gray-500">
        Tracked Wallets: <span class="font-mono font-semibold text-gray-600 dark:text-gray-300">{{ $trackedWallets }}</span>
    </div>
</div>
