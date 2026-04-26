<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPolymarketTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_displays_polymarket_credentials_section(): void
    {
        $response = $this->get(route('settings'));

        $response->assertOk();
        $response->assertSee('Polymarket Credentials');
        $response->assertSee('Simpan Kredensial');
        $response->assertDontSee('Private Key');
    }

    public function test_it_updates_polymarket_credentials_from_settings_form(): void
    {
        $response = $this->post(route('settings.polymarket.update'), [
            'address' => '0xabc123',
            'funder' => '0xdef456',
            'signature_type' => 2,
            'api_key' => 'api-key-123',
            'api_secret' => base64_encode('my-secret'),
            'api_passphrase' => 'my-passphrase',
        ]);

        $response->assertRedirect(route('settings'));
        $response->assertSessionHas('settings_success');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'polymarket.address',
            'is_encrypted' => false,
            'value' => '0xabc123',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'polymarket.funder',
            'is_encrypted' => false,
            'value' => '0xdef456',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'polymarket.signature_type',
            'is_encrypted' => false,
            'value' => '2',
        ]);

        $apiSecret = SystemSetting::query()->where('key', 'polymarket.api_secret')->firstOrFail();
        $apiPassphrase = SystemSetting::query()->where('key', 'polymarket.api_passphrase')->firstOrFail();

        $this->assertTrue($apiSecret->is_encrypted);
        $this->assertTrue($apiPassphrase->is_encrypted);
        $this->assertNotSame(base64_encode('my-secret'), $apiSecret->value);
        $this->assertNotSame('my-passphrase', $apiPassphrase->value);
        $this->assertDatabaseMissing('system_settings', ['key' => 'polymarket.private_key']);
    }

    public function test_it_can_clear_stored_sensitive_values_from_settings_form(): void
    {
        $this->post(route('settings.polymarket.update'), [
            'address' => '0xabc123',
            'funder' => '0xdef456',
            'signature_type' => 1,
            'api_key' => 'api-key-123',
            'api_secret' => base64_encode('my-secret'),
            'api_passphrase' => 'my-passphrase',
        ]);

        $response = $this->post(route('settings.polymarket.update'), [
            'address' => '0xabc123',
            'funder' => '0xdef456',
            'signature_type' => 1,
            'api_key' => 'api-key-123',
            'clear_api_secret' => '1',
            'clear_api_passphrase' => '1',
        ]);

        $response->assertRedirect(route('settings'));

        $this->assertDatabaseMissing('system_settings', ['key' => 'polymarket.api_secret']);
        $this->assertDatabaseMissing('system_settings', ['key' => 'polymarket.api_passphrase']);
    }
}
