<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PolymarketAuthService
{
    public function __construct(
        public PolymarketConfigService $configService,
        public SecretResolverService $secretResolverService,
        public SigningService $signingService
    ) {}

    public function buildL1Headers(string $timestamp, string $signature, string $address): array
    {
        return [
            'POLY_ADDRESS' => $address,
            'POLY_SIGNATURE' => $signature,
            'POLY_TIMESTAMP' => $timestamp,
            'POLY_NONCE' => '0',
        ];
    }

    /**
     * @param  array|string|null  $body
     * @return array<string, string>
     */
    public function buildL2Headers(string $method, string $requestPath, array|string|null $body = null): array
    {
        $config = $this->configService->tradingConfig();

        return $this->buildL2HeadersWithCredentials(
            $config['address'],
            $config['api_key'],
            $config['api_secret'],
            $config['api_passphrase'],
            $method,
            $requestPath,
            $body
        );
    }

    public function buildL2HeadersForAccount(
        PolymarketAccount $account,
        string $method,
        string $requestPath,
        array|string|null $body = null
    ): array {
        return $this->buildL2HeadersWithCredentials(
            $account->wallet_address,
            $account->api_key,
            $account->api_secret,
            $account->api_passphrase,
            $method,
            $requestPath,
            $body
        );
    }

    /**
     * @param  array|string|null  $body
     * @return array<string, string>
     */
    private function buildL2HeadersWithCredentials(
        ?string $address,
        ?string $apiKey,
        ?string $secret,
        ?string $passphrase,
        string $method,
        string $requestPath,
        array|string|null $body
    ): array {
        $address = $address === null ? null : trim($address);
        $apiKey = $apiKey === null ? null : trim($apiKey);
        $secret = $secret === null ? null : trim($secret);
        $passphrase = $passphrase === null ? null : trim($passphrase);

        if ($address === null || $apiKey === null || $secret === null || $passphrase === null) {
            throw new RuntimeException('Konfigurasi L2 Polymarket belum lengkap. Pastikan signer address, api key, secret, dan passphrase tersedia.');
        }

        $normalizedPath = $this->normalizePath($requestPath);
        $timestamp = $this->getServerTimestamp();
        $signature = $this->signL2Request(
            $secret,
            $timestamp,
            strtoupper($method),
            $normalizedPath,
            $this->normalizeBody($body)
        );

        return [
            'POLY_ADDRESS' => $address,
            'POLY_SIGNATURE' => $signature,
            'POLY_TIMESTAMP' => $timestamp,
            'POLY_API_KEY' => $apiKey,
            'POLY_PASSPHRASE' => $passphrase,
        ];
    }

    public function signL2Request(
        string $secret,
        string $timestamp,
        string $method,
        string $requestPath,
        string $body
    ): string {
        $normalizedPath = $this->normalizePath($requestPath);

        return $this->signingService->signL2Request(
            $secret,
            $timestamp,
            $method,
            $normalizedPath,
            $body
        );
    }

    /**
     * @return array{ok:bool,status:int,body:array}
     */
    public function validateL2Credentials(): array
    {
        $host = rtrim((string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'), '/');

        $response = Http::baseUrl($host)
            ->timeout((int) config('services.polymarket.timeout_seconds', 15))
            ->acceptJson()
            ->withHeaders($this->buildL2Headers('GET', '/data/orders'))
            ->get('/data/orders');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function getServerTimestamp(): string
    {
        $host = rtrim((string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'), '/');

        $response = Http::baseUrl($host)
            ->timeout((int) config('services.polymarket.timeout_seconds', 15))
            ->acceptJson()
            ->get('/time');

        if ($response->successful()) {
            $timestamp = $response->json();

            if (is_numeric($timestamp)) {
                return (string) $timestamp;
            }
        }

        return (string) now()->timestamp;
    }

    public function resolveSignerPrivateKey(?string $envKeyAlias = null): string
    {
        return $this->secretResolverService->resolvePrivateKey($envKeyAlias);
    }

    private function normalizePath(string $requestPath): string
    {
        return str_starts_with($requestPath, '/') ? $requestPath : '/'.$requestPath;
    }

    /**
     * @param  array|string|null  $body
     */
    private function normalizeBody(array|string|null $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }

        if (is_string($body)) {
            return $body;
        }

        return json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

}
