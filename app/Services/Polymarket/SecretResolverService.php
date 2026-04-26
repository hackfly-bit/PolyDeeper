<?php

namespace App\Services\Polymarket;

use RuntimeException;

class SecretResolverService
{
    public function resolvePrivateKey(?string $envKeyAlias = null): string
    {
        $resolvedKey = $this->readSecret($envKeyAlias);

        if ($resolvedKey === null || trim($resolvedKey) === '') {
            $source = $envKeyAlias === null
                ? 'POLYMARKET_SIGNER_PRIVATE_KEY'
                : $envKeyAlias;

            throw new RuntimeException('Private key signer Polymarket tidak ditemukan pada backend secret source: '.$source.'.');
        }

        return trim($resolvedKey);
    }

    private function readSecret(?string $envKeyAlias = null): ?string
    {
        if ($envKeyAlias !== null && trim($envKeyAlias) !== '') {
            $key = trim($envKeyAlias);

            return $this->readEnvironmentValue($key);
        }

        return $this->readEnvironmentValue('POLYMARKET_SIGNER_PRIVATE_KEY');
    }

    private function readEnvironmentValue(string $key): ?string
    {
        $serverValue = $_SERVER[$key] ?? null;
        if (is_string($serverValue) && trim($serverValue) !== '') {
            return $serverValue;
        }

        $envValue = $_ENV[$key] ?? null;
        if (is_string($envValue) && trim($envValue) !== '') {
            return $envValue;
        }

        $getEnvValue = getenv($key);
        if (is_string($getEnvValue) && trim($getEnvValue) !== '') {
            return $getEnvValue;
        }

        return null;
    }
}
