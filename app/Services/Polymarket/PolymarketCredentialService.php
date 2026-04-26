<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use RuntimeException;

class PolymarketCredentialService
{
    public function __construct(
        public SecretResolverService $secretResolverService,
        public PolymarketService $polymarketService,
        public PolymarketAccountAuditService $auditService
    ) {}

    /**
     * @param  array{
     *     api_key:string,
     *     api_secret:string,
     *     api_passphrase:string
     * }  $credentialPayload
     */
    public function generateOrStoreCredentials(PolymarketAccount $account, array $credentialPayload): PolymarketAccount
    {
        $account->update([
            'api_key' => trim($credentialPayload['api_key']),
            'api_secret' => trim($credentialPayload['api_secret']),
            'api_passphrase' => trim($credentialPayload['api_passphrase']),
            'credential_status' => 'active',
            'last_error_code' => null,
            'last_validated_at' => now(),
            'last_rotated_at' => now(),
        ]);
        $this->auditService->log(
            $account,
            'credential.store',
            'success',
            'Credential L2 account disimpan/dirotasi.'
        );

        return $account->refresh();
    }

    public function rotateCredentials(PolymarketAccount $account): PolymarketAccount
    {
        $account->update([
            'credential_status' => 'needs_rotation',
            'last_error_code' => null,
        ]);
        $this->auditService->log(
            $account,
            'credential.rotate',
            'info',
            'Credential account ditandai needs_rotation.'
        );

        return $account->refresh();
    }

    public function revokeCredentials(PolymarketAccount $account): PolymarketAccount
    {
        $account->update([
            'api_key' => null,
            'api_secret' => null,
            'api_passphrase' => null,
            'credential_status' => 'revoked',
            'last_error_code' => null,
        ]);
        $this->auditService->log(
            $account,
            'credential.revoke',
            'warning',
            'Credential account direvoke.'
        );

        return $account->refresh();
    }

    /**
     * @return array{ok:bool,status:int,message:string}
     */
    public function validateCredentials(PolymarketAccount $account): array
    {
        if (! $this->hasStoredCredentials($account)) {
            $account = $this->bootstrapCredentialsFromL1($account);
        }

        $result = $this->polymarketService->validateCredentials($account);



        $account->update([
            'credential_status' => $result['ok'] ? 'active' : 'validation_failed',
            'last_error_code' => $result['ok'] ? null : 'HTTP_'.$result['status'],
            'last_validated_at' => now(),
        ]);
        $this->auditService->log(
            $account,
            'credential.validate',
            $result['ok'] ? 'success' : 'warning',
            $result['ok'] ? 'Credential account valid.' : 'Credential account gagal validasi.',
            ['status' => $result['status']]
        );

        return [
            'ok' => $result['ok'],
            'status' => $result['status'],
            'message' => $result['ok']
                ? 'Credential valid dan siap dipakai.'
                : 'Credential tidak valid. Periksa private key signer dan konfigurasi account.',
        ];
    }

    public function ensureSignerPrivateKeyExists(PolymarketAccount $account): string
    {
        return $this->secretResolverService->resolvePrivateKey($account->env_key_name);
    }

    public function bootstrapCredentialsFromL1(PolymarketAccount $account, int $nonce = 0): PolymarketAccount
    {
        $privateKey = $this->ensureSignerPrivateKeyExists($account);
        $derived = $this->polymarketService->deriveApiCredentials($account, $privateKey, $nonce);

        if ($derived['ok']) {
            $storedAccount = $this->storeApiCredentials($account, $derived['body']);
            $this->auditService->log(
                $storedAccount,
                'credential.bootstrap',
                'success',
                'Credential L2 berhasil di-derive dari autentikasi L1.',
                ['status' => $derived['status']]
            );

            return $storedAccount;
        }

        $created = $this->polymarketService->createApiCredentials($account, $privateKey, $nonce);

        if (! $created['ok']) {
            $account->update([
                'credential_status' => 'validation_failed',
                'last_error_code' => 'HTTP_'.$created['status'],
            ]);
            $this->auditService->log(
                $account,
                'credential.bootstrap',
                'warning',
                'Bootstrap credential L2 dari autentikasi L1 gagal.',
                ['status' => $created['status']]
            );

            throw new RuntimeException($this->bootstrapFailureMessage($created['status'], $created['body']));
        }

        $storedAccount = $this->storeApiCredentials($account, $created['body']);
        $this->auditService->log(
            $storedAccount,
            'credential.bootstrap',
            'success',
            'Credential L2 baru berhasil dibuat dari autentikasi L1.',
            ['status' => $created['status']]
        );

        return $storedAccount;
    }

    /**
     * @param  array<string, mixed>  $credentialPayload
     */
    private function storeApiCredentials(PolymarketAccount $account, array $credentialPayload): PolymarketAccount
    {
        $apiKey = trim((string) ($credentialPayload['apiKey'] ?? $credentialPayload['api_key'] ?? ''));
        $apiSecret = trim((string) ($credentialPayload['secret'] ?? $credentialPayload['api_secret'] ?? ''));
        $apiPassphrase = trim((string) ($credentialPayload['passphrase'] ?? $credentialPayload['api_passphrase'] ?? ''));

        if ($apiKey === '' || $apiSecret === '' || $apiPassphrase === '') {
            throw new RuntimeException('Respons Polymarket tidak mengandung API key, secret, dan passphrase yang lengkap.');
        }

        return $this->generateOrStoreCredentials($account, [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'api_passphrase' => $apiPassphrase,
        ]);
    }

    private function hasStoredCredentials(PolymarketAccount $account): bool
    {
        return filled($account->api_key)
            && filled($account->api_secret)
            && filled($account->api_passphrase);
    }

    /**
     * @param  array<string, mixed>  $responseBody
     */
    private function bootstrapFailureMessage(int $status, array $responseBody): string
    {
        $message = (string) ($responseBody['error'] ?? $responseBody['message'] ?? '');

        if ($message !== '') {
            if (str_contains(strtolower($message), 'invalid l1 request headers')) {
                return 'Gagal membuat credential L2 dari autentikasi L1: header L1 tidak valid. Pastikan `wallet_address` adalah alamat signer Polygon yang sama dengan private key pada `env_key_name`, dan `funder_address` hanya dipakai untuk proxy wallet.';
            }

            return 'Gagal membuat credential L2 dari autentikasi L1: '.$message;
        }

        return 'Gagal membuat credential L2 dari autentikasi L1. HTTP status: '.$status.'.';
    }
}
