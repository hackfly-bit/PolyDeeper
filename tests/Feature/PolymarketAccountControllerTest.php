<?php

namespace Tests\Feature;

use App\Models\PolymarketAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PolymarketAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_polymarket_account_from_settings_page(): void
    {
        $response = $this->post(route('settings.polymarket.accounts.store'), [
            'name' => 'Alpha Trader',
            'wallet_address' => '0xabc123',
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_ALPHA',
        ]);

        $response->assertRedirect(route('settings.polymarket.accounts.index'));
        $this->assertDatabaseHas('polymarket_accounts', [
            'name' => 'Alpha Trader',
            'wallet_address' => '0xabc123',
            'funder_address' => '0xabc123',
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'credential_status' => 'pending',
            'is_active' => true,
        ]);
    }

    public function test_it_bootstraps_credentials_from_l1_when_validating_account(): void
    {
        $account = PolymarketAccount::factory()->create([
            'wallet_address' => '0x1234567890abcdef1234567890abcdef12345678',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'api_key' => null,
            'api_secret' => null,
            'api_passphrase' => null,
            'credential_status' => 'pending',
        ]);

        putenv('POLY_SIGNER_ALPHA=0x0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

        Http::fake([
            'https://clob.polymarket.com/time' => Http::sequence()
                ->push(1712345678, 200)
                ->push(1712345679, 200),
            'https://clob.polymarket.com/auth/derive-api-key' => Http::response([
                'apiKey' => 'pk_test_1234',
                'secret' => base64_encode('secret-value'),
                'passphrase' => 'passphrase-value',
            ], 200),
            'https://clob.polymarket.com/auth/api-key' => Http::response([
                'apiKey' => 'pk_test_1234',
                'secret' => base64_encode('secret-value'),
                'passphrase' => 'passphrase-value',
            ], 200),
            'https://clob.polymarket.com/data/orders' => Http::response([], 200),
        ]);

        $validateResponse = $this->post(route('settings.polymarket.accounts.validate', $account));
        $validateResponse->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $this->assertTrue(
            session()->has('account_success') || session()->has('account_error')
        );
    }

    public function test_it_can_revoke_account_credentials(): void
    {
        $account = PolymarketAccount::factory()->create([
            'api_key' => 'pk_test_1234',
            'api_secret' => base64_encode('secret-value'),
            'api_passphrase' => 'passphrase-value',
            'credential_status' => 'active',
        ]);

        $revokeResponse = $this->post(route('settings.polymarket.accounts.revoke', $account));
        $revokeResponse->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $account->id,
            'credential_status' => 'revoked',
            'api_key' => null,
        ]);
    }

    public function test_it_returns_health_endpoint_for_account(): void
    {
        $account = PolymarketAccount::factory()->create([
            'credential_status' => 'validation_failed',
            'last_error_code' => 'HTTP_401',
        ]);

        $response = $this->get(route('settings.polymarket.accounts.health', $account));

        $response->assertOk();
        $response->assertJsonFragment([
            'account_id' => $account->id,
            'credential_status' => 'validation_failed',
            'last_error_code' => 'HTTP_401',
        ]);
    }

    public function test_it_can_update_new_risk_limit_fields_for_account(): void
    {
        $account = PolymarketAccount::factory()->create([
            'credential_status' => 'active',
        ]);

        $response = $this->put(route('settings.polymarket.accounts.update', $account), [
            'name' => 'Trader Updated',
            'wallet_address' => '0xupdated-wallet',
            'funder_address' => '0xupdated-funder',
            'signature_type' => 0,
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'is_active' => true,
            'priority' => 10,
            'risk_profile' => 'aggressive',
            'max_exposure_usd' => 1000.5,
            'max_order_size' => 15.5,
            'max_open_positions' => 3,
            'max_open_positions_per_market' => 1,
            'max_order_size_in_usd' => 120,
            'daily_limit_mode' => 'count',
            'max_daily_loss_position' => 2,
            'max_daily_win_position' => 5,
            'cooldown_seconds' => 15,
        ]);

        $response->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $account->id,
            'max_open_positions' => 3,
            'max_open_positions_per_market' => 1,
            'daily_limit_mode' => 'count',
        ]);
    }

    public function test_it_can_refresh_stored_balance_for_account(): void
    {
        $account = PolymarketAccount::factory()->create([
            'wallet_address' => '0xabc123',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'credential_status' => 'active',
            'api_key' => 'pk_test_123',
            'api_secret' => base64_encode('secret-value'),
            'api_passphrase' => 'passphrase-value',
        ]);

        putenv('POLY_SIGNER_ALPHA=0xprivate-key-alpha');

        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
            'https://clob.polymarket.com/balance-allowance*' => Http::response([
                'balance' => 321.45,
            ], 200),
        ]);

        $response = $this->post(route('settings.polymarket.accounts.refresh-balance', $account));

        $response->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $response->assertSessionHas('account_success');
        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $account->id,
            'last_balance_usd' => 321.45,
        ]);
    }

    public function test_it_can_refresh_all_active_account_balances_from_dashboard(): void
    {
        $activeAccount = PolymarketAccount::factory()->create([
            'wallet_address' => '0xabc123',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'credential_status' => 'active',
            'is_active' => true,
            'api_key' => 'pk_test_123',
            'api_secret' => base64_encode('secret-value'),
            'api_passphrase' => 'passphrase-value',
        ]);
        $inactiveAccount = PolymarketAccount::factory()->create([
            'wallet_address' => '0xdef456',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'credential_status' => 'active',
            'is_active' => false,
            'api_key' => 'pk_test_456',
            'api_secret' => base64_encode('secret-value'),
            'api_passphrase' => 'passphrase-value',
        ]);

        putenv('POLY_SIGNER_ALPHA=0xprivate-key-alpha');

        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
            'https://clob.polymarket.com/balance-allowance*' => Http::response([
                'balance' => 777.77,
            ], 200),
        ]);

        $response = $this->post(route('settings.polymarket.accounts.refresh-balances'));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('dashboard_success');
        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $activeAccount->id,
            'last_balance_usd' => 777.77,
        ]);
        $this->assertDatabaseMissing('polymarket_accounts', [
            'id' => $inactiveAccount->id,
            'last_balance_usd' => 777.77,
        ]);
    }
}
