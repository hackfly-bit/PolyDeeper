<?php

namespace Tests\Feature\Unit;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolymarketConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_wallet_address_as_funder_for_eoa_accounts(): void
    {
        $account = app(PolymarketAccountService::class)->create([
            'name' => 'EOA Trader',
            'wallet_address' => '0xeoa-wallet',
            'funder_address' => null,
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_EOA',
        ]);

        $this->assertSame('0xeoa-wallet', $account->wallet_address);
        $this->assertSame('0xeoa-wallet', $account->funder_address);
    }

    public function test_it_keeps_funder_nullable_for_proxy_accounts_until_user_sets_it(): void
    {
        $account = app(PolymarketAccountService::class)->create([
            'name' => 'Proxy Trader',
            'wallet_address' => '0xproxy-wallet',
            'funder_address' => null,
            'signature_type' => 2,
            'env_key_name' => 'POLY_SIGNER_PROXY',
        ]);

        $this->assertNull($account->funder_address);
    }

    public function test_it_updates_runtime_fields_on_existing_account(): void
    {
        $account = PolymarketAccount::factory()->create([
            'name' => 'Alpha Trader',
            'wallet_address' => '0xalpha',
            'funder_address' => null,
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'priority' => 100,
            'risk_profile' => 'standard',
            'cooldown_seconds' => 0,
        ]);
        $updated = app(PolymarketAccountService::class)->update($account, [
            'name' => 'Alpha Trader v2',
            'wallet_address' => '0xalpha-updated',
            'funder_address' => null,
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_ALPHA_V2',
            'is_active' => true,
            'priority' => 10,
            'risk_profile' => 'aggressive',
            'max_exposure_usd' => 2500.50,
            'max_order_size' => 25.75,
            'cooldown_seconds' => 30,
        ]);

        $this->assertSame('Alpha Trader v2', $updated->name);
        $this->assertSame('0xalpha-updated', $updated->wallet_address);
        $this->assertSame('0xalpha-updated', $updated->funder_address);
        $this->assertSame('POLY_SIGNER_ALPHA_V2', $updated->env_key_name);
        $this->assertSame(10, $updated->priority);
        $this->assertSame('aggressive', $updated->risk_profile);
        $this->assertSame(30, $updated->cooldown_seconds);
    }
}
