@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @php
        $serverBadgeMap = [
            'healthy' => ['label' => 'Online', 'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'degraded' => ['label' => 'Perlu Cek', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'],
            'down' => ['label' => 'Offline', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
            'missing' => ['label' => 'Belum Diisi', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
        ];
        $accountBadgeMap = [
            'active' => ['label' => 'Active', 'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'needs_rotation' => ['label' => 'Needs Rotation', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'],
            'revoked' => ['label' => 'Revoked', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
            'validation_failed' => ['label' => 'Validation Failed', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
            'pending' => ['label' => 'Pending', 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'],
        ];
    @endphp
    <div class="card p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Polymarket Credentials</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Account aktif menjadi satu-satunya sumber auth trading. User cukup isi wallet address dan referensi private key backend, lalu tekan validate untuk membuat atau me-derive credential L2 secara otomatis.
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
                <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->name ?? 'Belum dipilih' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Credential Status</p>
                <p class="mt-2 font-semibold text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->credential_status ?? 'not_configured' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signer Address</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->wallet_address ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Funder Address</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->funder_address ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Signature Type</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->signature_type ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Private Key Source</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->env_key_name ?? '-' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Key</p>
                <p class="mt-2 font-mono text-gray-900 dark:text-white">{{ $selectedPolymarketMaskedApiKey ?? 'belum ada' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Secret</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->api_secret ? 'tersimpan' : 'belum ada' }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">API Passphrase</p>
                <p class="mt-2 text-gray-900 dark:text-white">{{ $selectedPolymarketAccount?->api_passphrase ? 'tersimpan' : 'belum ada' }}</p>
            </div>
        </div>

        <div class="mt-6">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Status Server Polymarket</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    Probe ringan ke endpoint utama Polymarket untuk melihat konektivitas dashboard ke CLOB, Gamma, dan Data API.
                </p>
            </div>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                @foreach ($polymarketServerStatuses as $server)
                    @php
                        $serverBadge = $serverBadgeMap[$server['state']] ?? ['label' => ucfirst((string) $server['state']), 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'];
                    @endphp
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-dark-border">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $server['name'] }}</p>
                                <p class="mt-1 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $server['host'] }}</p>
                            </div>
                            <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $serverBadge['class'] }}">
                                {{ $serverBadge['label'] }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Probe: <span class="font-mono">{{ $server['probe'] }}</span></p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">HTTP Status: <span class="font-mono">{{ $server['http_status'] ?? 'n/a' }}</span></p>
                        <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ $server['message'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        @if ($selectedPolymarketAccount)
            <div class="mt-6">
                <div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Akun Pada Wallet Ini</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        {{ $selectedPolymarketWalletAccounts->count() }} account lokal memakai signer address ini.
                    </p>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($selectedPolymarketWalletAccounts as $walletAccount)
                        @php
                            $accountBadge = $accountBadgeMap[$walletAccount->credential_status] ?? ['label' => ucfirst((string) $walletAccount->credential_status), 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'];
                        @endphp
                        <div class="rounded-xl border p-4 {{ $selectedPolymarketAccount->id === $walletAccount->id ? 'border-brand-200 bg-brand-50/40 dark:border-brand-800/50 dark:bg-brand-900/10' : 'border-gray-200 dark:border-dark-border' }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $walletAccount->name }}</p>
                                        @if ($selectedPolymarketAccount->id === $walletAccount->id)
                                            <span class="inline-flex rounded-full bg-brand-100 px-2 py-1 text-[11px] font-semibold text-brand-700 dark:bg-brand-900/30 dark:text-brand-300">
                                                Aktif Dipakai
                                            </span>
                                        @endif
                                        <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $walletAccount->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                                            {{ $walletAccount->is_active ? 'Trading On' : 'Trading Off' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $walletAccount->wallet_address }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-1 text-[11px] font-semibold {{ $accountBadge['class'] }}">
                                    {{ $accountBadge['label'] }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Env Key</p>
                                    <p class="mt-2 break-all font-mono text-gray-900 dark:text-white">{{ $walletAccount->env_key_name }}</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Validation</p>
                                    <p class="mt-2 text-gray-900 dark:text-white">{{ $walletAccount->last_validated_at?->toDateTimeString() ?? '-' }}</p>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">Last Error</p>
                                    <p class="mt-2 break-all text-gray-900 dark:text-white">{{ $walletAccount->last_error_code ?? '-' }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end">
                                <a href="{{ route('settings.polymarket.accounts.show', $walletAccount) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                                    Buka Detail Account
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
                            Belum ada account lokal yang memakai wallet address ini.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        @if ($selectedPolymarketAccount)
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('settings.polymarket.accounts.show', $selectedPolymarketAccount) }}" class="btn-primary text-sm px-4 py-2">
                    Buka Detail Account
                </a>
                <form method="POST" action="{{ route('settings.polymarket.accounts.validate', $selectedPolymarketAccount) }}">
                    @csrf
                    <button type="submit" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">
                        Validate Dan Sync Credential
                    </button>
                </form>
            </div>
        @endif
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
    @livewire(\App\Livewire\Dashboard\SettingsHealthCheck::class, [], key('settings-health-check'))
</div>
@endsection
