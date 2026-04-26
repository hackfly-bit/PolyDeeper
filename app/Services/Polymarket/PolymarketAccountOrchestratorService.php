<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;

class PolymarketAccountOrchestratorService
{
    private const ACTIVE_ACCOUNT_KEY = 'polymarket.active_account_id';

    public function pickActiveAccount(): ?PolymarketAccount
    {
        $preferredAccount = $this->preferredAccount();
        if ($preferredAccount instanceof PolymarketAccount && $this->isEligible($preferredAccount)) {
            return $preferredAccount;
        }

        /** @var Collection<int, PolymarketAccount> $accounts */
        $accounts = PolymarketAccount::query()
            ->where('is_active', true)
            ->whereIn('credential_status', ['active', 'needs_rotation'])
            ->where(function ($query): void {
                $query->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<=', now());
            })
            ->orderBy('priority')
            ->orderByDesc('last_validated_at')
            ->get();

        return $accounts->first();
    }

    public function preferredAccount(): ?PolymarketAccount
    {
        $accountId = (int) (SystemSetting::query()
            ->where('key', self::ACTIVE_ACCOUNT_KEY)
            ->value('value') ?? 0);

        if ($accountId <= 0) {
            return null;
        }

        return PolymarketAccount::query()->find($accountId);
    }

    public function selectActiveAccount(PolymarketAccount $account): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => self::ACTIVE_ACCOUNT_KEY],
            [
                'value' => (string) $account->id,
                'is_encrypted' => false,
            ]
        );
    }

    public function setCooldown(PolymarketAccount $account): void
    {
        if ($account->cooldown_seconds <= 0) {
            return;
        }

        $account->update([
            'cooldown_until' => now()->addSeconds($account->cooldown_seconds),
        ]);
    }

    private function isEligible(PolymarketAccount $account): bool
    {
        if (! $account->is_active) {
            return false;
        }

        if (! in_array($account->credential_status, ['active', 'needs_rotation'], true)) {
            return false;
        }

        return $account->cooldown_until === null || $account->cooldown_until->lte(now());
    }
}
