@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    @php
        $badgeMap = [
            'active' => ['label' => 'Active', 'class' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'needs_rotation' => ['label' => 'Needs Rotation', 'class' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'],
            'revoked' => ['label' => 'Revoked', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
            'validation_failed' => ['label' => 'Validation Failed', 'class' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'],
        ];
        $statusBadge = $badgeMap[$account->credential_status] ?? ['label' => ucfirst((string) $account->credential_status), 'class' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'];
    @endphp
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Account Detail: {{ $account->name }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Private key tetap di backend. Tombol validate akan melakukan autentikasi L1 lalu membuat atau me-derive credential L2 dan menyimpannya ke database.</p>
        </div>
        <a href="{{ route('settings.polymarket.accounts.index') }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Kembali</a>
    </div>

    @if (session('account_success'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-900/30 dark:bg-green-900/20 dark:text-green-300">
            {{ session('account_success') }}
        </div>
    @endif

    @if (session('account_error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300">
            {{ session('account_error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/30 dark:bg-red-900/20 dark:text-red-300">
            <p class="font-semibold">Validasi gagal:</p>
            <ul class="list-disc pl-4 mt-1 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card p-6">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Account Profile</h3>
        <form method="POST" action="{{ route('settings.polymarket.accounts.update', $account) }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf
            @method('PUT')
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Name</label>
                <input name="name" type="text" value="{{ old('name', $account->name) }}" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Wallet Address</label>
                <input name="wallet_address" type="text" value="{{ old('wallet_address', $account->wallet_address) }}" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Funder Address</label>
                <input name="funder_address" type="text" value="{{ old('funder_address', $account->funder_address) }}" placeholder="Opsional, auto wallet untuk signature type 0" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Signature Type</label>
                <select name="signature_type" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="0" @selected((string) old('signature_type', (string) $account->signature_type) === '0')>0 - EOA</option>
                    <option value="1" @selected((string) old('signature_type', (string) $account->signature_type) === '1')>1 - POLY_PROXY</option>
                    <option value="2" @selected((string) old('signature_type', (string) $account->signature_type) === '2')>2 - GNOSIS_SAFE</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Env Key Name</label>
                <input name="env_key_name" type="text" value="{{ old('env_key_name', $account->env_key_name) }}" required class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Priority</label>
                <input name="priority" type="number" min="1" max="10000" value="{{ old('priority', $account->priority) }}" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Risk Profile</label>
                <select name="risk_profile" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                    <option value="conservative" @selected(old('risk_profile', $account->risk_profile) === 'conservative')>conservative</option>
                    <option value="standard" @selected(old('risk_profile', $account->risk_profile) === 'standard')>standard</option>
                    <option value="aggressive" @selected(old('risk_profile', $account->risk_profile) === 'aggressive')>aggressive</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Max Exposure USD</label>
                <input name="max_exposure_usd" type="number" step="0.01" min="0" value="{{ old('max_exposure_usd', $account->max_exposure_usd) }}" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Max Order Size</label>
                <input name="max_order_size" type="number" step="0.000001" min="0" value="{{ old('max_order_size', $account->max_order_size) }}" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300">Cooldown (detik)</label>
                <input name="cooldown_seconds" type="number" min="0" max="3600" value="{{ old('cooldown_seconds', $account->cooldown_seconds) }}" class="mt-1 w-full rounded-xl border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
            </div>
            <div class="md:col-span-2">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active)) class="rounded border-gray-300 text-brand-600 focus:ring-brand-500" />
                    <span>Trading Enabled</span>
                </label>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="btn-primary text-sm px-4 py-2">Simpan Profile</button>
            </div>
        </form>
    </div>

    <div class="card p-6">
        <h3 class="text-base font-bold text-gray-900 dark:text-white">Credential</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
            Status:
            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusBadge['class'] }}">
                {{ $statusBadge['label'] }}
            </span>
        </p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last validation: {{ $account->last_validated_at?->toDateTimeString() ?? '-' }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Last error: {{ $account->last_error_code ?? '-' }}</p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">API Key: {{ $account->api_key ? substr($account->api_key, 0, 3).'****'.substr($account->api_key, -4) : '-' }}</p>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">PnL</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">${{ number_format((float) $metrics['pnl'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">Error Rate</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ number_format((float) $metrics['error_rate'], 2) }}%</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                <p class="text-xs text-gray-500 dark:text-gray-400">Throughput</p>
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $metrics['throughput'] }}</p>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:border-blue-900/30 dark:bg-blue-900/20 dark:text-blue-300">
            Input manual API key, secret, dan passphrase sudah dihapus. Credential L2 dikelola otomatis dari hasil autentikasi L1 saat validate.
        </div>

        <div class="mt-5 flex flex-wrap gap-2">
            <form method="POST" action="{{ route('settings.polymarket.accounts.validate', $account) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Validate Dan Sync Credential</button>
            </form>
            <form method="POST" action="{{ route('settings.polymarket.accounts.rotate', $account) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Mark Needs Rotation</button>
            </form>
            <form method="POST" action="{{ route('settings.polymarket.accounts.revoke', $account) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-red-200 px-3 py-2 text-sm font-semibold text-red-700 dark:border-red-900/40 dark:text-red-300">Revoke Credential</button>
            </form>
            <form method="POST" action="{{ route('settings.polymarket.accounts.disable-trading', $account) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-yellow-200 px-3 py-2 text-sm font-semibold text-yellow-700 dark:border-yellow-900/40 dark:text-yellow-300">Disable Trading</button>
            </form>
            <form method="POST" action="{{ route('settings.polymarket.accounts.enable-trading', $account) }}">
                @csrf
                <button type="submit" class="rounded-xl border border-green-200 px-3 py-2 text-sm font-semibold text-green-700 dark:border-green-900/40 dark:text-green-300">Enable Trading</button>
            </form>
            <a href="{{ route('settings.polymarket.accounts.health', $account) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Health Endpoint</a>
        </div>
    </div>
</div>
@endsection
