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
     *     wallet_address:string,
     *     funder_address?:?string,
     *     signature_type:int,
     *     env_key_name:string,
     *     priority?:?int,
     *     risk_profile?:?string,
     *     max_exposure_usd?:?float,
     *     max_order_size?:?float,
     *     max_open_positions?:?int,
     *     max_open_positions_per_market?:?int,
     *     max_order_size_in_usd?:?float,
     *     daily_limit_mode?:?string,
     *     max_daily_loss_position?:?float,
     *     max_daily_win_position?:?float,
     *     cooldown_seconds?:?int
     * }  $payload
     */
    public function create(array $payload): PolymarketAccount
    {
        $name = trim($payload['name']);
        $walletAddress = trim((string) ($payload['wallet_address'] ?? ''));
        $funderAddress = $this->normalizeFunderAddress(
            $payload['funder_address'] ?? null,
            $payload['signature_type'],
            $walletAddress
        );

        $account = PolymarketAccount::query()->create([
            'name' => $name,
            'account_slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'wallet_address' => $walletAddress,
            'funder_address' => $funderAddress,
            'signature_type' => $payload['signature_type'],
            'env_key_name' => trim((string) ($payload['env_key_name'] ?? '')),
            'priority' => $payload['priority'] ?? 100,
            'risk_profile' => $payload['risk_profile'] ?? 'standard',
            'max_exposure_usd' => $payload['max_exposure_usd'] ?? null,
            'max_order_size' => $payload['max_order_size'] ?? null,
            'max_open_positions' => $payload['max_open_positions'] ?? 0,
            'max_open_positions_per_market' => $payload['max_open_positions_per_market'] ?? 0,
            'max_order_size_in_usd' => $payload['max_order_size_in_usd'] ?? 0,
            'daily_limit_mode' => $payload['daily_limit_mode'] ?? 'count',
            'max_daily_loss_position' => $payload['max_daily_loss_position'] ?? 0,
            'max_daily_win_position' => $payload['max_daily_win_position'] ?? 0,
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
     *     wallet_address:string,
     *     funder_address?:?string,
     *     signature_type:int,
     *     env_key_name:string,
     *     is_active?:bool,
     *     priority?:?int,
     *     risk_profile?:?string,
     *     max_exposure_usd?:?float,
     *     max_order_size?:?float,
     *     max_open_positions?:?int,
     *     max_open_positions_per_market?:?int,
     *     max_order_size_in_usd?:?float,
     *     daily_limit_mode?:?string,
     *     max_daily_loss_position?:?float,
     *     max_daily_win_position?:?float,
     *     cooldown_seconds?:?int
     * }  $payload
     */
    public function update(PolymarketAccount $account, array $payload): PolymarketAccount
    {
        $walletAddress = trim((string) ($payload['wallet_address'] ?? ''));
        $funderAddress = $this->normalizeFunderAddress(
            $payload['funder_address'] ?? null,
            $payload['signature_type'],
            $walletAddress
        );

        $account->update([
            'name' => trim($payload['name']),
            'wallet_address' => $walletAddress,
            'funder_address' => $funderAddress,
            'signature_type' => $payload['signature_type'],
            'env_key_name' => trim((string) ($payload['env_key_name'] ?? '')),
            'is_active' => $payload['is_active'] ?? $account->is_active,
            'priority' => $payload['priority'] ?? $account->priority,
            'risk_profile' => $payload['risk_profile'] ?? $account->risk_profile,
            'max_exposure_usd' => $payload['max_exposure_usd'] ?? $account->max_exposure_usd,
            'max_order_size' => $payload['max_order_size'] ?? $account->max_order_size,
            'max_open_positions' => $payload['max_open_positions'] ?? $account->max_open_positions,
            'max_open_positions_per_market' => $payload['max_open_positions_per_market'] ?? $account->max_open_positions_per_market,
            'max_order_size_in_usd' => $payload['max_order_size_in_usd'] ?? $account->max_order_size_in_usd,
            'daily_limit_mode' => $payload['daily_limit_mode'] ?? $account->daily_limit_mode,
            'max_daily_loss_position' => $payload['max_daily_loss_position'] ?? $account->max_daily_loss_position,
            'max_daily_win_position' => $payload['max_daily_win_position'] ?? $account->max_daily_win_position,
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

    public function refreshStoredBalance(PolymarketAccount $account, float $balanceUsd): PolymarketAccount
    {
        $account->update([
            'last_balance_usd' => $balanceUsd,
            'last_balance_refreshed_at' => now(),
        ]);
        $this->auditService->log(
            $account,
            'account.balance.refresh',
            'success',
            'Saldo account berhasil diperbarui.',
            ['last_balance_usd' => $balanceUsd]
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

    private function normalizeFunderAddress(?string $funderAddress, int $signatureType, string $walletAddress): ?string
    {
        $normalizedFunderAddress = $funderAddress === null ? null : trim($funderAddress);

        if ($normalizedFunderAddress !== null && $normalizedFunderAddress !== '') {
            return $normalizedFunderAddress;
        }

        if ($signatureType === 0 && $walletAddress !== '') {
            return $walletAddress;
        }

        return null;
    }
}
