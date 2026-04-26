@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="card p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Polymarket Credentials</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Private key signer hanya dari backend env/vault. Kredensial L2 disimpan terenkripsi di database.
                </p>
            </div>
            <a href="{{ route('settings.polymarket.accounts.index') }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                Kelola Polymarket Accounts
            </a>
        </div>

        @if (session('settings_success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/30 dark:bg-green-900/20 dark:text-green-300">
                {{ session('settings_success') }}
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

        <form method="POST" action="{{ route('settings.polymarket.select-account') }}" class="mt-5">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_auto] gap-4 items-end">
                <div>
                    <label for="polymarket_account_id" class="text-xs font-semibold text-gray-600 dark:text-gray-300">Akun Polymarket Yang Digunakan</label>
                    <select id="polymarket_account_id" name="polymarket_account_id" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                        @forelse ($polymarketAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('polymarket_account_id', $selectedPolymarketAccount?->id) == $account->id)>
                                {{ $account->name }} - {{ $account->wallet_address ?? 'tanpa wallet' }} - {{ $account->credential_status }}
                            </option>
                        @empty
                            <option value="">Belum ada account tersedia</option>
                        @endforelse
                    </select>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary text-sm px-4 py-2" @disabled($polymarketAccounts->isEmpty())>Gunakan Account Ini</button>
                </div>
            </div>
        </form>

        <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Account Aktif</p>
                <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $polymarketConfig['account_name'] ?? 'Belum dipilih' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Credential Status</p>
                <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $polymarketConfig['credential_status'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signer Address</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $polymarketConfig['address'] ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Funder Address</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $polymarketConfig['funder'] ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signature Type</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $polymarketConfig['signature_type'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Key</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $polymarketConfig['masked_api_key'] ?? 'belum ada' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Secret</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $polymarketConfig['has_api_secret'] ? 'tersimpan' : 'belum ada' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Passphrase</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $polymarketConfig['has_api_passphrase'] ? 'tersimpan' : 'belum ada' }}</p>
            </div>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Runtime Configuration</h2>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">App Env</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['app_env'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Queue Connection</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['queue_connection'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Cache Store</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['cache_store'] }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-dark-border p-4">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Redis Client</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $runtime['redis_client'] ?? 'n/a' }}</p>
            </div>
        </div>
    </div>
    <div class="card p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Health Check</h2>
        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
            Redis Status:
            <span class="{{ $runtime['redis_reachable'] ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $runtime['redis_reachable'] ? 'reachable' : 'unavailable' }}
            </span>
        </p>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Pending Jobs: <span class="font-mono">{{ number_format($runtime['jobs_pending']) }}</span></p>
        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">Failed Jobs: <span class="font-mono">{{ number_format($runtime['jobs_failed']) }}</span></p>
        @if (!empty($runtime['redis_error']))
            <div class="mt-4 rounded-xl border border-red-200 bg-red-50 dark:bg-red-500/10 dark:border-red-500/20 p-4">
                <p class="text-sm text-red-700 dark:text-red-300">{{ $runtime['redis_error'] }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
