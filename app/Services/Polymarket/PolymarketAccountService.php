<?php

namespace App\Services\Polymarket;

use App\Models\ExecutionLog;
use App\Models\PolymarketAccount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class PolymarketAccountService
{
    public function __construct(
        public PolymarketAccountAuditService $auditService
    ) {}

    /**
     * @param  array{
     *     name:string,
     *     wallet_address?:?string,
     *     funder_address?:?string,
     *     signature_type:int,
     *     env_key_name?:?string,
     *     vault_key_ref?:?string,
     *     priority?:?int,
     *     risk_profile?:?string,
     *     max_exposure_usd?:?float,
     *     max_order_size?:?float,
     *     cooldown_seconds?:?int
     * }  $payload
     */
    public function create(array $payload): PolymarketAccount
    {
        $name = trim($payload['name']);

        $account = PolymarketAccount::query()->create([
            'name' => $name,
            'account_slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'wallet_address' => $payload['wallet_address'] ?? null,
            'funder_address' => $payload['funder_address'] ?? null,
            'signature_type' => $payload['signature_type'],
            'env_key_name' => $payload['env_key_name'] ?? null,
            'vault_key_ref' => $payload['vault_key_ref'] ?? null,
            'priority' => $payload['priority'] ?? 100,
            'risk_profile' => $payload['risk_profile'] ?? 'standard',
            'max_exposure_usd' => $payload['max_exposure_usd'] ?? null,
            'max_order_size' => $payload['max_order_size'] ?? null,
            'cooldown_seconds' => $payload['cooldown_seconds'] ?? 0,
            'credential_status' => 'pending',
            'is_active' => true,
        ]);

        $this->auditService->log(
            $account,
            'account.create',
            'success',
            'Polymarket account baru berhasil dibuat.'
        );

        return $account;
    }

    /**
     * @param  array{
     *     name:string,
     *     wallet_address?:?string,
     *     funder_address?:?string,
     *     signature_type:int,
     *     env_key_name?:?string,
     *     vault_key_ref?:?string,
     *     is_active?:bool,
     *     priority?:?int,
     *     risk_profile?:?string,
     *     max_exposure_usd?:?float,
     *     max_order_size?:?float,
     *     cooldown_seconds?:?int
     * }  $payload
     */
    public function update(PolymarketAccount $account, array $payload): PolymarketAccount
    {
        $account->update([
            'name' => trim($payload['name']),
            'wallet_address' => $payload['wallet_address'] ?? null,
            'funder_address' => $payload['funder_address'] ?? null,
            'signature_type' => $payload['signature_type'],
            'env_key_name' => $payload['env_key_name'] ?? null,
            'vault_key_ref' => $payload['vault_key_ref'] ?? null,
            'is_active' => $payload['is_active'] ?? $account->is_active,
            'priority' => $payload['priority'] ?? $account->priority,
            'risk_profile' => $payload['risk_profile'] ?? $account->risk_profile,
            'max_exposure_usd' => $payload['max_exposure_usd'] ?? $account->max_exposure_usd,
            'max_order_size' => $payload['max_order_size'] ?? $account->max_order_size,
            'cooldown_seconds' => $payload['cooldown_seconds'] ?? $account->cooldown_seconds,
        ]);

        $this->auditService->log(
            $account,
            'account.update',
            'success',
            'Profile Polymarket account diperbarui.'
        );

        return $account->refresh();
    }

    public function disableTrading(PolymarketAccount $account): PolymarketAccount
    {
        $account->update(['is_active' => false]);
        $this->auditService->log($account, 'trading.disable', 'warning', 'Kill switch account diaktifkan.');

        return $account->refresh();
    }

    public function enableTrading(PolymarketAccount $account): PolymarketAccount
    {
        $account->update(['is_active' => true]);
        $this->auditService->log($account, 'trading.enable', 'success', 'Trading account diaktifkan kembali.');

        return $account->refresh();
    }

    public function loadActiveAccount(): ?PolymarketAccount
    {
        return PolymarketAccount::query()
            ->where('is_active', true)
            ->whereIn('credential_status', ['active', 'needs_rotation'])
            ->orderByDesc('last_validated_at')
            ->first();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return PolymarketAccount::query()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * @return array{
     *     pnl:float,
     *     error_rate:float,
     *     throughput:int
     * }
     */
    public function metrics(PolymarketAccount $account): array
    {
        $orders = $account->orders()->get(['status', 'price', 'size']);
        $throughput = $orders->count();

        $filled = $orders->whereIn('status', ['filled', 'submitted']);
        $failed = $orders->where('status', 'failed');

        $pnl = (float) $filled->sum(function ($order) {
            return (float) $order->price * (float) $order->size;
        });
        $errorRate = $throughput === 0 ? 0.0 : round(($failed->count() / $throughput) * 100, 2);

        $runtimeErrors = ExecutionLog::query()
            ->where('wallet_address', $account->wallet_address)
            ->where('status', 'error')
            ->count();

        return [
            'pnl' => $pnl,
            'error_rate' => round($errorRate + ($runtimeErrors > 0 ? 0.1 : 0.0), 2),
            'throughput' => $throughput,
        ];
    }
}
