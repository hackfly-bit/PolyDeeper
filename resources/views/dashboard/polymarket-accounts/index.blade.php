@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="card p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Polymarket Accounts</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Buat account dengan data dasar auth: nama, wallet address, signature type, funder address bila perlu, dan referensi private key backend.</p>
            </div>
        </div>

        @if (session('account_success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/30 dark:bg-green-900/20 dark:text-green-300">
                {{ session('account_success') }}
            </div>
        @endif

        @if (session('account_error'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300">
                {{ session('account_error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300">
                <p class="font-semibold">Validasi gagal:</p>
                <ul class="list-disc pl-4 mt-1 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.polymarket.accounts.store') }}" class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Account Name</label>
                <input name="name" type="text" value="{{ old('name') }}" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Wallet Address</label>
                <input name="wallet_address" type="text" value="{{ old('wallet_address') }}" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Funder Address</label>
                <input name="funder_address" type="text" value="{{ old('funder_address') }}" placeholder="Opsional, auto diisi wallet untuk signature type 0" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Signature Type</label>
                <select name="signature_type" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="0" @selected((string) old('signature_type', '0') === '0')>0 - EOA</option>
                    <option value="1" @selected((string) old('signature_type') === '1')>1 - POLY_PROXY</option>
                    <option value="2" @selected((string) old('signature_type') === '2')>2 - GNOSIS_SAFE</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Env Key Name</label>
                <input name="env_key_name" type="text" value="{{ old('env_key_name') }}" required placeholder="Contoh: POLYMARKET_PK_MAIN" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn-primary text-sm px-4 py-2">Create Account</button>
            </div>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-dark-border">
            <h3 class="text-base font-bold text-gray-900 dark:text-white">Accounts List</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase tracking-wider text-gray-500 bg-gray-50/70 dark:bg-dark-bg/40">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Wallet</th>
                        <th class="px-4 py-3">Key Source</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Trading</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-dark-border">
                    @forelse ($accounts as $account)
                        @php
                            $badgeMap = [
                                'active' => ['label' => 'Active', 'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
                                'needs_rotation' => ['label' => 'Needs Rotation', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'],
                                'revoked' => ['label' => 'Revoked', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
                                'validation_failed' => ['label' => 'Validation Failed', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
                            ];
                            $badge = $badgeMap[$account->credential_status] ?? ['label' => ucfirst((string) $account->credential_status), 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'];
                        @endphp
                        <tr>
                            <td class="px-4 py-4">{{ $account->name }}</td>
                            <td class="px-4 py-4 font-mono text-xs">{{ $account->wallet_address ?? '-' }}</td>
                            <td class="px-4 py-4 font-mono text-xs">{{ $account->env_key_name ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $badge['class'] }}">
                                    {{ $badge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-4">{{ $account->is_active ? 'enabled' : 'disabled' }}</td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('settings.polymarket.accounts.show', $account) }}" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada polymarket account.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100 dark:border-dark-border">
            {{ $accounts->links() }}
        </div>
    </div>
</div>
@endsection
