<div class="card p-6">
    @php
        $badgeMap = [
            'healthy' => ['label' => 'Healthy', 'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'degraded' => ['label' => 'Perlu Cek', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'],
            'down' => ['label' => 'Down', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
            'missing' => ['label' => 'Missing', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
            'pending' => ['label' => 'Belum Dites', 'class' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
        ];
        $overallBadge = $badgeMap[$overallStatus['state']] ?? $badgeMap['pending'];
    @endphp

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Health Check Interaktif</h2>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $overallBadge['class'] }}">
                    {{ $overallStatus['label'] }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                Jalankan test kesiapan Redis, queue, database, cache, dan endpoint Polymarket untuk memastikan sistem siap dipakai.
            </p>
            @if ($lastRunAt)
                <p class="mt-2 text-xs font-mono text-gray-500 dark:text-gray-400">Terakhir dites: {{ $lastRunAt }}</p>
            @endif
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="runAllChecks" wire:loading.attr="disabled" class="btn-primary px-4 py-2 text-sm">
                Test Semua Sistem
            </button>
            <button type="button" wire:click="runCheck('redis')" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Test Redis
            </button>
            <button type="button" wire:click="runCheck('queue')" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Test Queue
            </button>
            <button type="button" wire:click="runCheck('database')" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Test Database
            </button>
            <button type="button" wire:click="runCheck('cache')" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Test Cache
            </button>
            <button type="button" wire:click="runCheck('polymarket')" wire:loading.attr="disabled" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Test Endpoint
            </button>
        </div>
    </div>

    <div wire:loading.flex class="mt-4 items-center gap-2 text-sm text-brand-600 dark:text-brand-300">
        <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-brand-500"></span>
        Menjalankan pengecekan sistem...
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @foreach ($checks as $check)
            @php
                $badge = $badgeMap[$check['state']] ?? $badgeMap['pending'];
                $meta = $check['meta'] ?? [];
            @endphp
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $check['label'] }}</h3>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $check['message'] }}</p>
                    </div>
                    <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $badge['class'] }}">
                        {{ $badge['label'] }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>Checked: <span class="font-mono">{{ $check['checked_at'] ?? '-' }}</span></span>
                    <span>Latency: <span class="font-mono">{{ $check['duration_ms'] ?? '-' }} ms</span></span>
                </div>

                @if (isset($meta['connection']))
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Connection: <span class="font-mono">{{ $meta['connection'] }}</span></p>
                @endif

                @if (isset($meta['store']))
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Store: <span class="font-mono">{{ $meta['store'] }}</span></p>
                @endif

                @if (isset($meta['client']))
                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Client: <span class="font-mono">{{ $meta['client'] }}</span></p>
                @endif

                @if (isset($meta['response']))
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Response: <span class="font-mono">{{ $meta['response'] }}</span></p>
                @endif

                @if (isset($meta['pending_jobs']) || isset($meta['failed_jobs']))
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Pending: <span class="font-mono">{{ number_format((int) ($meta['pending_jobs'] ?? 0)) }}</span>
                        |
                        Failed: <span class="font-mono">{{ number_format((int) ($meta['failed_jobs'] ?? 0)) }}</span>
                    </p>
                @endif

                @if (! empty($meta['endpoints']) && is_array($meta['endpoints']))
                    <div class="mt-4 space-y-2">
                        @foreach ($meta['endpoints'] as $endpoint)
                            @php
                                $endpointBadge = $badgeMap[$endpoint['state']] ?? $badgeMap['pending'];
                            @endphp
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $endpoint['name'] }}</p>
                                        <p class="mt-1 break-all font-mono text-[11px] text-gray-500 dark:text-gray-400">{{ $endpoint['host'] }}</p>
                                    </div>
                                    <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $endpointBadge['class'] }}">
                                        {{ $endpointBadge['label'] }}
                                    </span>
                                </div>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Probe: <span class="font-mono">{{ $endpoint['probe'] }}</span></p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">HTTP Status: <span class="font-mono">{{ $endpoint['http_status'] ?? 'n/a' }}</span></p>
                                <p class="mt-2 text-xs text-gray-700 dark:text-gray-300">{{ $endpoint['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
