<?php

namespace Tests\Feature\Unit;

use App\Services\Polymarket\PolymarketAuthService;
use App\Services\Polymarket\PolymarketConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PolymarketAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_l2_headers_using_hmac_signature_from_docs(): void
    {
        config([
            'services.polymarket.address' => '0xenv-address',
            'services.polymarket.api_key' => 'env-api-key',
            'services.polymarket.api_secret' => base64_encode('env-secret'),
            'services.polymarket.api_passphrase' => 'env-passphrase',
            'services.polymarket.timeout_seconds' => 15,
            'services.polymarket.clob_host' => 'https://clob.polymarket.com',
        ]);

        app(PolymarketConfigService::class)->storeTradingConfig([
            'address' => '0xabc123',
            'api_key' => 'test-api-key',
            'api_secret' => base64_encode('test-secret'),
            'api_passphrase' => 'test-passphrase',
        ]);

        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
        ]);

        $headers = app(PolymarketAuthService::class)->buildL2Headers('POST', '/order', [
            'foo' => 'bar',
        ]);

        $expectedSignature = base64_encode(
            hash_hmac(
                'sha256',
                '1712345678POST/order{"foo":"bar"}',
                'test-secret',
                true
            )
        );

        $this->assertSame('0xabc123', $headers['POLY_ADDRESS']);
        $this->assertSame('test-api-key', $headers['POLY_API_KEY']);
        $this->assertSame('test-passphrase', $headers['POLY_PASSPHRASE']);
        $this->assertSame('1712345678', $headers['POLY_TIMESTAMP']);
        $this->assertSame($expectedSignature, $headers['POLY_SIGNATURE']);
    }

    public function test_it_throws_when_required_l2_credentials_are_missing(): void
    {
        config([
            'services.polymarket.address' => null,
            'services.polymarket.api_key' => null,
            'services.polymarket.api_secret' => null,
            'services.polymarket.api_passphrase' => null,
        ]);

        $this->expectException(RuntimeException::class);

        app(PolymarketAuthService::class)->buildL2Headers('GET', '/data/orders');
    }

    public function test_it_throws_when_l2_secret_is_not_valid_base64(): void
    {
        config([
            'services.polymarket.address' => '0xenv-address',
            'services.polymarket.api_key' => 'env-api-key',
            'services.polymarket.api_secret' => 'not-base64-secret',
            'services.polymarket.api_passphrase' => 'env-passphrase',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('base64');

        app(PolymarketAuthService::class)->buildL2Headers('GET', '/data/orders');
    }
}
