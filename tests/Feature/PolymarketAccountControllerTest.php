<?php

namespace Tests\Feature;

use App\Models\PolymarketAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolymarketAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_polymarket_account_from_settings_page(): void
    {
        $response = $this->post(route('settings.polymarket.accounts.store'), [
            'name' => 'Alpha Trader',
            'wallet_address' => '0xabc123',
            'funder_address' => '0xdef456',
            'signature_type' => 1,
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'vault_key_ref' => 'vault://alpha',
        ]);

        $response->assertRedirect(route('settings.polymarket.accounts.index'));
        $this->assertDatabaseHas('polymarket_accounts', [
            'name' => 'Alpha Trader',
            'wallet_address' => '0xabc123',
            'signature_type' => 1,
            'credential_status' => 'pending',
            'is_active' => true,
        ]);
    }

    public function test_it_can_store_validate_and_revoke_account_credentials(): void
    {
        $account = PolymarketAccount::factory()->create([
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'api_key' => null,
            'api_secret' => null,
            'api_passphrase' => null,
            'credential_status' => 'pending',
        ]);

        $storeResponse = $this->post(route('settings.polymarket.accounts.credentials.store', $account), [
            'api_key' => 'pk_test_1234',
            'api_secret' => base64_encode('secret-value'),
            'api_passphrase' => 'passphrase-value',
        ]);

        $storeResponse->assertRedirect(route('settings.polymarket.accounts.show', $account));

        $this->assertDatabaseHas('polymarket_accounts', [
            'id' => $account->id,
            'credential_status' => 'active',
        ]);

        putenv('POLY_SIGNER_ALPHA=0xprivate-key-alpha');

        $validateResponse = $this->post(route('settings.polymarket.accounts.validate', $account));
        $validateResponse->assertRedirect(route('settings.polymarket.accounts.show', $account));
        $validateResponse->assertSessionHas('account_success');

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
