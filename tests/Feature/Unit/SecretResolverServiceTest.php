<?php

namespace Tests\Feature\Unit;

use App\Services\Polymarket\SecretResolverService;
use RuntimeException;
use Tests\TestCase;

class SecretResolverServiceTest extends TestCase
{
    public function test_it_resolves_private_key_from_default_backend_env(): void
    {
        putenv('POLYMARKET_SIGNER_PRIVATE_KEY=default-private-key');

        $resolvedKey = app(SecretResolverService::class)->resolvePrivateKey();

        $this->assertSame('default-private-key', $resolvedKey);
    }

    public function test_it_resolves_private_key_from_alias_env(): void
    {
        putenv('POLYMARKET_SIGNER_PRIVATE_KEY=');
        putenv('POLY_SIGNER_BOT_A=alias-private-key');

        $resolvedKey = app(SecretResolverService::class)->resolvePrivateKey('POLY_SIGNER_BOT_A');

        $this->assertSame('alias-private-key', $resolvedKey);
    }

    public function test_it_throws_when_alias_private_key_is_missing(): void
    {
        putenv('POLYMARKET_SIGNER_PRIVATE_KEY=');
        putenv('POLY_SIGNER_NOT_FOUND=');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Private key signer Polymarket tidak ditemukan');

        app(SecretResolverService::class)->resolvePrivateKey('POLY_SIGNER_NOT_FOUND');
    }
}
