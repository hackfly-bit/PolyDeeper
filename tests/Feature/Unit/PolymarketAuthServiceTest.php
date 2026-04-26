<?php

namespace Tests\Feature\Unit;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAuthService;
use App\Services\Polymarket\SigningService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PolymarketAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNER_PRIVATE_KEY = '0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80';

    private const SIGNER_ADDRESS = '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266';

    public function test_it_builds_l2_headers_using_hmac_signature_from_docs(): void
    {
        config([
            'services.polymarket.timeout_seconds' => 15,
            'services.polymarket.clob_host' => 'https://clob.polymarket.com',
        ]);
        $account = PolymarketAccount::factory()->make([
            'wallet_address' => '0xabc123',
            'api_key' => 'test-api-key',
            'api_secret' => base64_encode('test-secret'),
            'api_passphrase' => 'test-passphrase',
        ]);

        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
        ]);

        $headers = app(PolymarketAuthService::class)->buildL2HeadersForAccount($account, 'POST', '/order', [
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
        $account = PolymarketAccount::factory()->make([
            'wallet_address' => '0xabc123',
            'api_key' => null,
            'api_secret' => null,
            'api_passphrase' => null,
        ]);

        $this->expectException(RuntimeException::class);

        app(PolymarketAuthService::class)->buildL2HeadersForAccount($account, 'GET', '/data/orders');
    }

    public function test_it_throws_when_l2_secret_is_not_valid_base64(): void
    {
        $account = PolymarketAccount::factory()->make([
            'wallet_address' => '0xenv-address',
            'api_key' => 'env-api-key',
            'api_secret' => 'not-base64-secret',
            'api_passphrase' => 'env-passphrase',
        ]);
        Http::fake([
            'https://clob.polymarket.com/time' => Http::response(1712345678, 200),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('base64');

        app(PolymarketAuthService::class)->buildL2HeadersForAccount($account, 'GET', '/data/orders');
    }

    public function test_it_throws_runtime_exception_when_server_time_endpoint_is_unreachable(): void
    {
        Http::fake([
            'https://clob.polymarket.com/time' => static function (): void {
                throw new ConnectionException('cURL error 60: SSL certificate problem');
            },
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/time');

        app(PolymarketAuthService::class)->getServerTimestamp();
    }

    public function test_it_derives_signer_address_from_private_key(): void
    {
        $address = app(SigningService::class)->addressFromPrivateKey(self::SIGNER_PRIVATE_KEY);

        $this->assertSame(self::SIGNER_ADDRESS, $address);
    }

    public function test_it_builds_l1_headers_with_matching_signer_address(): void
    {
        $account = PolymarketAccount::factory()->make([
            'wallet_address' => '0xF39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
        ]);

        $headers = app(PolymarketAuthService::class)->buildL1HeadersForAccount(
            $account,
            self::SIGNER_PRIVATE_KEY,
            0,
            '1712345678'
        );

        $this->assertSame(self::SIGNER_ADDRESS, $headers['POLY_ADDRESS']);
        $this->assertSame('1712345678', $headers['POLY_TIMESTAMP']);
        $this->assertSame('0', $headers['POLY_NONCE']);
        $this->assertMatchesRegularExpression('/^0x[a-f0-9]{130}$/', $headers['POLY_SIGNATURE']);
        $this->assertContains(substr($headers['POLY_SIGNATURE'], -2), ['1b', '1c']);
    }

    public function test_it_throws_when_wallet_address_does_not_match_signer_private_key(): void
    {
        $account = PolymarketAccount::factory()->make([
            'wallet_address' => '0x0000000000000000000000000000000000000001',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('POLY_ADDRESS');

        app(PolymarketAuthService::class)->buildL1HeadersForAccount(
            $account,
            self::SIGNER_PRIVATE_KEY,
            0,
            '1712345678'
        );
    }
}
