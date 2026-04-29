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

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Judul Market</th>
                        <th class="px-4 py-3">Kategori</th>
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
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada market dari wallet yang dipantau.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            {{ $markers->links() }}
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
