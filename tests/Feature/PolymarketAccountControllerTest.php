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
            'wallet_address' => '0xabc123',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'api_key' => null,
            'api_secret' => null,
            'api_passphrase' => null,
            'credential_status' => 'pending',
        ]);

        putenv('POLY_SIGNER_ALPHA=0xprivate-key-alpha');

        Http::fake([
            'https://clob.polymarket.com/time' => Http::sequence()
                ->push(1712345678, 200)
                ->push(1712345679, 200),
            'https://clob.polymarket.com/auth/derive-api-key' => Http::response([
                'apiKey' => 'pk_test_1234',
                'secret' => base64_encode('secret-value'),
                'passphrase' => 'passphrase-value',
            ], 200),
            'https://clob.polymarket.com/data/orders' => Http::response([], 200),
        ]);

        $validateResponse = $this->post(route('settings.polymarket.accounts.validate', $account));
        $validateResponse->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $validateResponse->assertSessionHas('account_success');

        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $account->id,
            'api_key' => 'pk_test_1234',
            'credential_status' => 'active',
            'last_error_code' => null,
        ]);
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
}
