<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PolymarketAuthService
{
    public function __construct(
        public SecretResolverService $secretResolverService,
        public SigningService $signingService
    ) {}

    /**
     * @return array<string, string>
     */
    public function buildL1HeadersForAccount(
        PolymarketAccount $account,
        string $privateKey,
        int $nonce = 0,
        ?string $timestamp = null
    ): array
    {
        $address = trim((string) $account->wallet_address);

        if ($address === '') {
            throw new RuntimeException('Wallet address account Polymarket wajib diisi sebelum validate credential.');
        }

        $signerAddress = $this->signingService->addressFromPrivateKey($privateKey);

        if (! str_starts_with(strtolower($address), '0x')) {
            $address = '0x'.ltrim($address, '0x');
        }

        if (! hash_equals(strtolower($address), strtolower($signerAddress))) {
            throw new RuntimeException(
                'Menurut Polymarket Doc, `POLY_ADDRESS` pada L1 harus alamat signer Polygon. '.
                'Pastikan `wallet_address` sama dengan alamat dari private key pada `env_key_name`, dan gunakan `funder_address` hanya untuk proxy wallet.'
            );
        }

        $resolvedTimestamp = $timestamp ?? $this->getServerTimestamp();
        $signature = $this->signingService->signClobAuthMessage(
            $signerAddress,
            $resolvedTimestamp,
            $nonce,
            $privateKey
        );

        return [
            'POLY_ADDRESS' => $signerAddress,
            'POLY_SIGNATURE' => $signature,
            'POLY_TIMESTAMP' => $resolvedTimestamp,
            'POLY_NONCE' => (string) $nonce,
        ];
    }

    /**
     * @param  array|string|null  $body
     * @return array<string, string>
     */
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
    public function validateL2CredentialsForAccount(PolymarketAccount $account): array
    {
        try {
            $response = $this->request()
                ->withHeaders($this->buildL2HeadersForAccount($account, 'GET', '/data/orders'))
                ->get('/data/orders');
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->connectionFailureMessage('/data/orders'), previous: $exception);
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function getServerTimestamp(): string
    {
        try {
            $response = $this->request()->get('/time');
        } catch (ConnectionException $exception) {
            throw new RuntimeException($this->connectionFailureMessage('/time'), previous: $exception);
        }

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

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'), '/'))
            ->timeout((int) config('services.polymarket.timeout_seconds', 15))
            ->acceptJson()
            ->withOptions([
                // 'verify' => $this->tlsVerifyOption(),
                'verify' => false,
            ]);
    }

    /**
     * @return bool|string
     */
    private function tlsVerifyOption(): bool|string
    {
        $caBundle = trim((string) config('services.polymarket.ca_bundle', ''));

        if ($caBundle !== '') {
            return $caBundle;
        }

        return (bool) config('services.polymarket.tls_verify', true);
    }

    private function connectionFailureMessage(string $path): string
    {
        return sprintf(
            'Gagal terhubung ke endpoint Polymarket `%s`. Menurut Polymarket Doc, endpoint ini diperlukan untuk flow autentikasi CLOB. Periksa CA certificate PHP/cURL di Windows, atau set `POLYMARKET_CA_BUNDLE`. Untuk troubleshooting lokal sementara, Anda juga bisa set `POLYMARKET_TLS_VERIFY=false`.',
            $path
        );
    }

}
