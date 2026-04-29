@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-4">
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">History Signal & Execution</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Riwayat lengkap signal dan eksekusi, dengan filter untuk investigasi cepat.</p>
        </div>

        <div class="p-4 border-b border-gray-100 dark:border-dark-border bg-gray-50/60 dark:bg-dark-bg/30">
            <form method="GET" action="{{ route('history') }}" class="grid grid-cols-1 gap-3 md:grid-cols-6">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="type">Jenis Log</label>
                    <select id="type" name="type" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                        <option value="all" @selected($selectedType === 'all')>Semua</option>
                        <option value="signal" @selected($selectedType === 'signal')>Signal</option>
                        <option value="execution" @selected($selectedType === 'execution')>Execution</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="status">Status</label>
                    <select id="status" name="status" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                        <option value="all" @selected($selectedStatus === 'all')>Semua</option>
                        <option value="buy" @selected($selectedStatus === 'buy')>Signal Buy</option>
                        <option value="sell" @selected($selectedStatus === 'sell')>Signal Sell</option>
                        <option value="success" @selected($selectedStatus === 'success')>Execution Success</option>
                        <option value="failed" @selected($selectedStatus === 'failed')>Execution Failed</option>
                        <option value="pending" @selected($selectedStatus === 'pending')>Execution Pending</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="wallet_id">Wallet</label>
                    <select id="wallet_id" name="wallet_id" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                        <option value="0">Semua Wallet</option>
                        @foreach ($walletOptions as $wallet)
                            <option value="{{ $wallet->id }}" @selected($selectedWalletId === $wallet->id)>
                                {{ $wallet->name !== '' ? $wallet->name : $wallet->address }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="from">Dari Tanggal</label>
                    <input id="from" name="from" type="date" value="{{ $fromDate }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="to">Sampai Tanggal</label>
                    <input id="to" name="to" type="date" value="{{ $toDate }}" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400" for="q">Keyword</label>
                    <input id="q" name="q" type="text" value="{{ $search }}" placeholder="market, message, action" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm dark:border-dark-border dark:bg-dark-surface">
                </div>
                <div class="md:col-span-6 flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Terapkan Filter</button>
                    <a href="{{ route('history') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">Reset</a>
                </div>
            </form>
        </div>
    </div>

    @if ($selectedType !== 'execution')
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-gray-100 dark:border-dark-border">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Signal Logs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Market</th>
                            <th class="px-4 py-3">Arah</th>
                            <th class="px-4 py-3">Strength</th>
                            <th class="px-4 py-3">Wallet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                        @forelse ($signals as $signal)
                            <tr>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $signal->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $signalConditionId = (string) ($signal->market_id ?: $signal->condition_id ?: '');
                                        $signalMarketTitle = trim((string) ($signal->market?->question ?? ($marketTitlesByCondition[$signal->market_id] ?? $marketTitlesByCondition[$signal->condition_id] ?? '')));
                                    @endphp
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $signalMarketTitle !== '' ? $signalMarketTitle : 'Market tanpa judul' }}</div>
                                    <div class="mt-1 text-xs font-mono text-gray-400 dark:text-gray-500">{{ $signalConditionId !== '' ? $signalConditionId : '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="badge {{ $signal->direction > 0 ? 'badge-success' : 'badge-danger' }}">{{ $signal->direction > 0 ? 'BUY' : 'SELL' }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono">{{ number_format((float) $signal->strength, 3) }}</td>
                                <td class="px-4 py-3 text-xs">{{ $signal->wallet?->name ?: ($signal->wallet?->address ?: '-') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada signal yang sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-dark-border">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $signals->firstItem() ?? 0 }} - {{ $signals->lastItem() ?? 0 }} dari {{ $signals->total() }} signal
                    </p>
                    <div>
                        @if ($signals->hasPages())
                            <nav class="flex items-center justify-end gap-2" aria-label="Pagination Signal History">
                                @if ($signals->onFirstPage())
                                    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Prev</span>
                                @else
                                    <a href="{{ $signals->previousPageUrl() }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">Prev</a>
                                @endif

                                @php
                                    $signalsStartPage = max(1, $signals->currentPage() - 1);
                                    $signalsEndPage = min($signals->lastPage(), $signals->currentPage() + 1);
                                @endphp

                                @if ($signalsStartPage > 1)
                                    <a href="{{ $signals->url(1) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">1</a>
                                    @if ($signalsStartPage > 2)
                                        <span class="px-1 text-xs text-gray-400">...</span>
                                    @endif
                                @endif

                                @foreach (range($signalsStartPage, $signalsEndPage) as $pageNumber)
                                    @if ($pageNumber === $signals->currentPage())
                                        <span class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">{{ $pageNumber }}</span>
                                    @else
                                        <a href="{{ $signals->url($pageNumber) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">{{ $pageNumber }}</a>
                                    @endif
                                @endforeach

                                @if ($signalsEndPage < $signals->lastPage())
                                    @if ($signalsEndPage < $signals->lastPage() - 1)
                                        <span class="px-1 text-xs text-gray-400">...</span>
                                    @endif
                                    <a href="{{ $signals->url($signals->lastPage()) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">{{ $signals->lastPage() }}</a>
                                @endif

                                @if ($signals->hasMorePages())
                                    <a href="{{ $signals->nextPageUrl() }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">Next</a>
                                @else
                                    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Next</span>
                                @endif
                            </nav>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($selectedType !== 'signal')
        <div class="card overflow-hidden">
            <div class="p-4 border-b border-gray-100 dark:border-dark-border">
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Execution Logs</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Stage</th>
                            <th class="px-4 py-3">Market</th>
                            <th class="px-4 py-3">Action</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                        @forelse ($executions as $execution)
                            <tr>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $execution->occurred_at?->format('Y-m-d H:i:s') ?: $execution->created_at?->format('Y-m-d H:i:s') }}</td>
                                <td class="px-4 py-3 text-xs font-mono">{{ $execution->stage ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $executionConditionId = (string) ($execution->market_id ?? '');
                                        $executionMarketTitle = trim((string) ($marketTitlesByCondition[$executionConditionId] ?? ''));
                                    @endphp
                                    <div class="font-semibold text-gray-900 dark:text-white">{{ $executionMarketTitle !== '' ? $executionMarketTitle : 'Market tanpa judul' }}</div>
                                    <div class="mt-1 text-xs font-mono text-gray-400 dark:text-gray-500">{{ $executionConditionId !== '' ? $executionConditionId : '-' }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ $execution->action ?: '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $statusValue = strtolower((string) ($execution->status ?? ''));
                                    @endphp
                                    <span class="{{ $statusValue === 'success' ? 'badge badge-success' : ($statusValue === 'failed' ? 'badge badge-danger' : 'badge') }}">
                                        {{ $execution->status ?: '-' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $execution->message ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada execution log yang sesuai filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100 dark:border-dark-border">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Menampilkan {{ $executions->firstItem() ?? 0 }} - {{ $executions->lastItem() ?? 0 }} dari {{ $executions->total() }} execution log
                    </p>
                    <div>
                        @if ($executions->hasPages())
                            <nav class="flex items-center justify-end gap-2" aria-label="Pagination Execution History">
                                @if ($executions->onFirstPage())
                                    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Prev</span>
                                @else
                                    <a href="{{ $executions->previousPageUrl() }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">Prev</a>
                                @endif

                                @php
                                    $executionsStartPage = max(1, $executions->currentPage() - 1);
                                    $executionsEndPage = min($executions->lastPage(), $executions->currentPage() + 1);
                                @endphp

                                @if ($executionsStartPage > 1)
                                    <a href="{{ $executions->url(1) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">1</a>
                                    @if ($executionsStartPage > 2)
                                        <span class="px-1 text-xs text-gray-400">...</span>
                                    @endif
                                @endif

                                @foreach (range($executionsStartPage, $executionsEndPage) as $pageNumber)
                                    @if ($pageNumber === $executions->currentPage())
                                        <span class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">{{ $pageNumber }}</span>
                                    @else
                                        <a href="{{ $executions->url($pageNumber) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">{{ $pageNumber }}</a>
                                    @endif
                                @endforeach

                                @if ($executionsEndPage < $executions->lastPage())
                                    @if ($executionsEndPage < $executions->lastPage() - 1)
                                        <span class="px-1 text-xs text-gray-400">...</span>
                                    @endif
                                    <a href="{{ $executions->url($executions->lastPage()) }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">{{ $executions->lastPage() }}</a>
                                @endif

                                @if ($executions->hasMorePages())
                                    <a href="{{ $executions->nextPageUrl() }}" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50">Next</a>
                                @else
                                    <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Next</span>
                                @endif
                            </nav>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
