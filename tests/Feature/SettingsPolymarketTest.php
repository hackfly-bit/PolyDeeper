<?php

namespace Tests\Feature;

use App\Models\PolymarketAccount;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsPolymarketTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_displays_polymarket_credentials_section(): void
    {
        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
            'https://gamma-api.polymarket.com/markets*' => Http::response([['id' => 'market-1']], 200),
            'https://data-api.polymarket.com*' => Http::response(['status' => 'ok'], 200),
        ]);

        $account = PolymarketAccount::factory()->create([
            'name' => 'Alpha Trader',
            'wallet_address' => '0xabc123',
            'env_key_name' => 'POLY_SIGNER_ALPHA',
            'credential_status' => 'active',
        ]);
        PolymarketAccount::factory()->create([
            'name' => 'Alpha Secondary',
            'wallet_address' => '0xAbC123',
            'env_key_name' => 'POLY_SIGNER_ALPHA_SECONDARY',
            'credential_status' => 'needs_rotation',
        ]);
        SystemSetting::query()->create([
            'key' => 'polymarket.active_account_id',
            'value' => (string) $account->id,
            'is_encrypted' => false,
        ]);

        $response = $this->get(route('settings'));

        $response->assertOk();
        $response->assertSee('Polymarket Credentials');
        $response->assertSee('Validate Dan Sync Credential');
        $response->assertSee('Alpha Trader');
        $response->assertSee('Status Server Polymarket');
        $response->assertSee('CLOB API');
        $response->assertSee('Gamma API');
        $response->assertSee('Data API');
        $response->assertSee('Akun Pada Wallet Ini');
        $response->assertSee('2 account lokal memakai signer address ini.');
        $response->assertSee('Alpha Secondary');
        $response->assertDontSee('Simpan Kredensial');
    }

    public function test_it_can_select_active_polymarket_account_from_settings(): void
    {
        $account = PolymarketAccount::factory()->create([
            'credential_status' => 'active',
            'is_active' => true,
        ]);

        $response = $this->post(route('settings.polymarket.select-account'), [
            'polymarket_account_id' => $account->id,
        ]);

        $response->assertRedirect(route('settings'));
        $response->assertSessionHas('settings_success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'polymarket.active_account_id',
            'is_encrypted' => false,
            'value' => (string) $account->id,
        ]);
    }

    public function test_settings_page_falls_back_to_best_eligible_account_when_selected_account_is_invalid(): void
    {
        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
            'https://gamma-api.polymarket.com/markets*' => Http::response([['id' => 'market-1']], 200),
            'https://data-api.polymarket.com*' => Http::response(['status' => 'ok'], 200),
        ]);

        $inactiveAccount = PolymarketAccount::factory()->create([
            'name' => 'Inactive Trader',
            'credential_status' => 'active',
            'is_active' => false,
        ]);
        $eligibleAccount = PolymarketAccount::factory()->create([
            'name' => 'Eligible Trader',
            'credential_status' => 'active',
            'is_active' => true,
        ]);
        SystemSetting::query()->create([
            'key' => 'polymarket.active_account_id',
            'value' => (string) $inactiveAccount->id,
            'is_encrypted' => false,
        ]);

        $response = $this->get(route('settings'));

        $response->assertOk();
        $response->assertSee('Eligible Trader');
    }
}
