<div x-data="{ refreshing: false }" x-on:livewire-refresh.window="refreshing = true; $wire.refresh().then(() => refreshing = false)" class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Pipeline Flow</h2>
        <span class="text-xs text-gray-500 dark:text-gray-400">
            <span x-show="refreshing" x-cloak>⟳</span>
            Polling: 5s
        </span>
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
            <div class="bg-white dark:bg-gray-800 rounded-xl p-3 border border-gray-200 dark:border-gray-700 hover:shadow-md transition-shadow">
                <div class="text-2xl font-bold text-gray-900 dark:text-white mb-1">
                    {{ $pipeline[$key] ?? 0 }}
                </div>
                <div class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $label }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-tight">{{ $desc }}</div>
            </div>
        @endforeach
    </div>

    <div class="text-xs text-gray-400 dark:text-gray-500">
        Tracked Wallets: <span class="font-mono font-semibold text-gray-600 dark:text-gray-300">{{ $trackedWallets }}</span>
    </div>
</div>
