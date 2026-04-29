@extends('layouts.app')

@section('content')
<div
    x-data="{
        showDetailModal: false,
        markerDetail: {
            title: '',
            volume: '-',
            time_remaining: '-',
            rules: '-',
            context: '-',
        },
        openDetail(marker) {
            this.markerDetail = marker.detail;
            this.markerDetail.title = marker.title;
            this.showDetailModal = true;
        },
    }"
    class="max-w-7xl mx-auto"
>
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Marker</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Daftar market aktif yang ditradingkan wallet yang dipantau.</p>
        </div>

        <div class="p-4 border-b border-gray-100 dark:border-dark-border bg-gray-50/60 dark:bg-dark-bg/30">
            <form method="GET" action="{{ route('markers') }}" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="wallet_id" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Wallet</label>
                    <select
                        id="wallet_id"
                        name="wallet_id"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-400 focus:outline-none dark:border-dark-border dark:bg-dark-surface dark:text-gray-100"
                    >
                        <option value="0">Semua Wallet</option>
                        @foreach ($walletOptions as $walletOption)
                            @php
                                $walletLabel = $walletOption->name !== '' ? $walletOption->name : $walletOption->address;
                            @endphp
                            <option value="{{ $walletOption->id }}" @selected($selectedWalletId === $walletOption->id)>
                                {{ $walletLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="status" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-400 focus:outline-none dark:border-dark-border dark:bg-dark-surface dark:text-gray-100"
                    >
                        <option value="all" @selected($selectedStatus === 'all')>Semua</option>
                        <option value="open" @selected($selectedStatus === 'open')>Open</option>
                        <option value="closed" @selected($selectedStatus === 'closed')>Closed</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="q" class="mb-1 block text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cari Judul Market</label>
                    <div class="flex gap-2">
                        <input
                            id="q"
                            name="q"
                            type="text"
                            value="{{ $searchTitle }}"
                            placeholder="Contoh: bitcoin, election, fed"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700 focus:border-brand-400 focus:outline-none dark:border-dark-border dark:bg-dark-surface dark:text-gray-100"
                        >
                        <button
                            type="submit"
                            class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                        >
                            Filter
                        </button>
                        <a
                            href="{{ route('markers') }}"
                            class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                        >
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Judul Market</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Nama Wallet</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($markers as $marker)
                        <tr>
                            <td class="px-4 py-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ $marker['title'] }}</div>
                                <div class="mt-1 text-xs font-mono text-gray-400 dark:text-gray-500">{{ $marker['condition_id'] }}</div>
                            </td>
                            <td class="px-4 py-4">{{ $marker['category'] }}</td>
                            <td class="px-4 py-4">
                                @if ($marker['status'] === 'Open')
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300">Open</span>
                                @else
                                    <span class="inline-flex rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/20 dark:text-rose-300">Closed</span>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="text-gray-700 dark:text-gray-200">{{ $marker['wallet_names'] !== '' ? $marker['wallet_names'] : '-' }}</div>
                                @if ($marker['wallet_count'] >= 2)
                                    <div class="mt-1 inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-700 dark:bg-brand-900/20 dark:text-brand-300">
                                        Merged {{ $marker['wallet_count'] }} wallet
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($marker['market_url'] !== null)
                                        <a
                                            href="{{ $marker['market_url'] }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="rounded-lg border border-blue-200 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-900/40 dark:text-blue-300 dark:hover:bg-blue-900/20"
                                        >
                                            Buka Polymarket
                                        </a>
                                    @endif
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                        @click='openDetail(@json($marker))'
                                    >
                                        Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada market yang sesuai filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Menampilkan {{ $markers->firstItem() ?? 0 }} - {{ $markers->lastItem() ?? 0 }} dari {{ $markers->total() }} market
                </p>
                <div>
                    @if ($markers->hasPages())
                        <nav class="flex items-center justify-end gap-2" aria-label="Pagination Marker">
                            @if ($markers->onFirstPage())
                                <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Prev</span>
                            @else
                                <a
                                    href="{{ $markers->previousPageUrl() }}"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                                >
                                    Prev
                                </a>
                            @endif

                            @php
                                $startPage = max(1, $markers->currentPage() - 1);
                                $endPage = min($markers->lastPage(), $markers->currentPage() + 1);
                            @endphp

                            @if ($startPage > 1)
                                <a
                                    href="{{ $markers->url(1) }}"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                                >
                                    1
                                </a>
                                @if ($startPage > 2)
                                    <span class="px-1 text-xs text-gray-400">...</span>
                                @endif
                            @endif

                            @foreach (range($startPage, $endPage) as $pageNumber)
                                @if ($pageNumber === $markers->currentPage())
                                    <span class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white">{{ $pageNumber }}</span>
                                @else
                                    <a
                                        href="{{ $markers->url($pageNumber) }}"
                                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                                    >
                                        {{ $pageNumber }}
                                    </a>
                                @endif
                            @endforeach

                            @if ($endPage < $markers->lastPage())
                                @if ($endPage < $markers->lastPage() - 1)
                                    <span class="px-1 text-xs text-gray-400">...</span>
                                @endif
                                <a
                                    href="{{ $markers->url($markers->lastPage()) }}"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                                >
                                    {{ $markers->lastPage() }}
                                </a>
                            @endif

                            @if ($markers->hasMorePages())
                                <a
                                    href="{{ $markers->nextPageUrl() }}"
                                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-dark-border dark:text-gray-200 dark:hover:bg-dark-bg/50"
                                >
                                    Next
                                </a>
                            @else
                                <span class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-400 dark:border-dark-border dark:text-gray-500">Next</span>
                            @endif
                        </nav>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div
        x-show="showDetailModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="showDetailModal = false"
    >
        <div class="w-full max-w-2xl rounded-2xl bg-white p-5 shadow-xl dark:bg-dark-surface">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white" x-text="markerDetail.title"></h3>
                <button
                    type="button"
                    class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    @click="showDetailModal = false"
                >
                    Tutup
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-dark-border dark:bg-dark-bg/40">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Volume</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" x-text="markerDetail.volume"></p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3 dark:border-dark-border dark:bg-dark-bg/40">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Waktu Tersisa</p>
                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white" x-text="markerDetail.time_remaining"></p>
                </div>
            </div>

            <div class="mt-4 space-y-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Aturan</p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap break-words" x-text="markerDetail.rules"></p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Konteks Pasar</p>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap break-words" x-text="markerDetail.context"></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
