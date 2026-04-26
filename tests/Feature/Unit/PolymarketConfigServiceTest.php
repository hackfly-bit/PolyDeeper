<?php

namespace Tests\Feature\Unit;

use App\Models\SystemSetting;
use App\Services\Polymarket\PolymarketConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolymarketConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_database_values_for_dynamic_polymarket_settings(): void
    {
        config([
            'services.polymarket.address' => '0xenv-address',
            'services.polymarket.funder' => '0xenv-funder',
            'services.polymarket.api_key' => 'env-api-key',
            'services.polymarket.api_secret' => 'ZW52LXNlY3JldA==',
            'services.polymarket.api_passphrase' => 'env-passphrase',
            'services.polymarket.signature_type' => 0,
        ]);

        $service = app(PolymarketConfigService::class);
        $service->storeTradingConfig([
            'address' => '0xdb-address',
            'funder' => '0xdb-funder',
            'api_key' => 'db-api-key',
            'api_secret' => 'ZGItc2VjcmV0',
            'api_passphrase' => 'db-passphrase',
            'signature_type' => 2,
        ]);

        $config = $service->tradingConfig();
        $secretSetting = SystemSetting::query()->where('key', 'polymarket.api_secret')->firstOrFail();

        $this->assertSame('0xdb-address', $config['address']);
        $this->assertSame('0xdb-funder', $config['funder']);
        $this->assertSame('db-api-key', $config['api_key']);
        $this->assertSame('ZGItc2VjcmV0', $config['api_secret']);
        $this->assertSame('db-passphrase', $config['api_passphrase']);
        $this->assertSame(2, $config['signature_type']);
        $this->assertTrue($secretSetting->is_encrypted);
        $this->assertNotSame('ZGItc2VjcmV0', $secretSetting->value);
    }

    public function test_it_uses_funder_as_signer_for_eoa_when_address_is_missing(): void
    {
        $service = app(PolymarketConfigService::class);
        $service->storeTradingConfig([
            'funder' => '0xeoa-funder',
            'signature_type' => 0,
        ]);

        $config = $service->tradingConfig();

        $this->assertSame('0xeoa-funder', $config['address']);
    }

    public function test_it_does_not_use_env_fallback_for_sensitive_credentials(): void
    {
        config([
            'services.polymarket.api_key' => 'env-api-key',
            'services.polymarket.api_secret' => base64_encode('env-secret'),
            'services.polymarket.api_passphrase' => 'env-passphrase',
        ]);

        $config = app(PolymarketConfigService::class)->tradingConfig();

        $this->assertNull($config['api_key']);
        $this->assertNull($config['api_secret']);
        $this->assertNull($config['api_passphrase']);
    }
}
