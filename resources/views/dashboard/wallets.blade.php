@extends('layouts.app')

@section('content')
<div
    x-data="{
        showCreateModal: false,
        showEditModal: false,
        showDeleteModal: false,
        editAction: '',
        deleteAction: '',
        walletForm: {
            id: '',
            address: '',
            weight: '',
            win_rate: '',
            roi: '',
            last_active: ''
        },
        openCreateModal() {
            this.walletForm = { id: '', address: '', weight: '', win_rate: '', roi: '', last_active: '' };
            this.showCreateModal = true;
        },
        openEditModal(wallet) {
            this.walletForm = {
                id: wallet.id,
                address: wallet.address,
                weight: wallet.weight,
                win_rate: wallet.win_rate,
                roi: wallet.roi,
                last_active: wallet.last_active ?? ''
            };
            this.editAction = '{{ url('/wallets') }}/' + wallet.id;
            this.showEditModal = true;
        },
        openDeleteModal(wallet) {
            this.walletForm = {
                id: wallet.id,
                address: wallet.address,
                weight: wallet.weight,
                win_rate: wallet.win_rate,
                roi: wallet.roi,
                last_active: wallet.last_active ?? ''
            };
            this.deleteAction = '{{ url('/wallets') }}/' + wallet.id;
            this.showDeleteModal = true;
        }
    }"
    class="max-w-7xl mx-auto"
>
    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Tracked Wallets</h2>
                <button type="button" class="btn-primary text-xs px-3 py-2" @click="openCreateModal()">Tambah Wallet</button>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Wallet score untuk proses agregasi signal.</p>
        </div>

        @if (session('wallet_success'))
            <div class="mx-5 mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/30 dark:bg-green-900/20 dark:text-green-300">
                {{ session('wallet_success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mx-5 mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300">
                <p class="font-semibold">Validasi gagal:</p>
                <ul class="list-disc pl-4 mt-1 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Address</th>
                        <th class="px-4 py-3">Weight</th>
                        <th class="px-4 py-3">Win Rate</th>
                        <th class="px-4 py-3">ROI</th>
                        <th class="px-4 py-3">Last Active</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($wallets as $wallet)
                        <tr>
                            <td class="px-4 py-4 font-mono text-xs">{{ $wallet->address }}</td>
                            <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->weight, 2) }}</td>
                            <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->win_rate, 2) }}</td>
                            <td class="px-4 py-4 font-mono">{{ number_format((float) $wallet->roi, 2) }}</td>
                            <td class="px-4 py-4 text-gray-500 dark:text-gray-400">{{ $wallet->last_active?->diffForHumans() ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                        @click="openEditModal({
                                            id: {{ $wallet->id }},
                                            address: @js($wallet->address),
                                            weight: @js((string) $wallet->weight),
                                            win_rate: @js((string) $wallet->win_rate),
                                            roi: @js((string) $wallet->roi),
                                            last_active: @js($wallet->last_active?->format('Y-m-d\TH:i'))
                                        })"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-900/40 dark:text-red-300 dark:hover:bg-red-900/20"
                                        @click="openDeleteModal({
                                            id: {{ $wallet->id }},
                                            address: @js($wallet->address),
                                            weight: @js((string) $wallet->weight),
                                            win_rate: @js((string) $wallet->win_rate),
                                            roi: @js((string) $wallet->roi),
                                            last_active: @js($wallet->last_active?->format('Y-m-d\TH:i'))
                                        })"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada wallet terdaftar.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            {{ $wallets->links() }}
        </div>
    </div>

    <div x-show="showCreateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateModal = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl dark:bg-dark-surface">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Tambah Wallet</h3>
            <form method="POST" action="{{ route('wallets.store') }}" class="mt-4 space-y-3">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Address</label>
                    <input name="address" type="text" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Weight</label>
                        <input name="weight" type="number" step="0.01" min="0" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Win Rate</label>
                        <input name="win_rate" type="number" step="0.01" min="0" max="100" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">ROI</label>
                        <input name="roi" type="number" step="0.01" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Last Active</label>
                    <input name="last_active" type="datetime-local" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="showCreateModal = false">Batal</button>
                    <button type="submit" class="btn-primary text-sm px-4 py-2">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showEditModal = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl dark:bg-dark-surface">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Edit Wallet</h3>
            <form method="POST" :action="editAction" class="mt-4 space-y-3">
                @csrf
                @method('PUT')
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Address</label>
                    <input name="address" type="text" required x-model="walletForm.address" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Weight</label>
                        <input name="weight" type="number" step="0.01" min="0" required x-model="walletForm.weight" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Win Rate</label>
                        <input name="win_rate" type="number" step="0.01" min="0" max="100" required x-model="walletForm.win_rate" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">ROI</label>
                        <input name="roi" type="number" step="0.01" required x-model="walletForm.roi" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Last Active</label>
                    <input name="last_active" type="datetime-local" x-model="walletForm.last_active" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="showEditModal = false">Batal</button>
                    <button type="submit" class="btn-primary text-sm px-4 py-2">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-dark-surface">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Hapus Wallet</h3>
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Yakin ingin menghapus wallet <span class="font-mono text-xs" x-text="walletForm.address"></span>?</p>
            <form method="POST" :action="deleteAction" class="mt-5 flex justify-end gap-2">
                @csrf
                @method('DELETE')
                <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="showDeleteModal = false">Batal</button>
                <button type="submit" class="rounded-xl border border-red-200 bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:border-red-900/40">Hapus</button>
            </form>
        </div>
    </div>
</div>
@endsection
