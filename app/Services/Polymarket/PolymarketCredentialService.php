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
        $this->ensureSignerPrivateKeyExists($account);

        if (
            $account->api_key === null
            || $account->api_secret === null
            || $account->api_passphrase === null
        ) {
            throw new RuntimeException('Kredensial L2 belum lengkap untuk account ini.');
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
            'message' => $result['ok'] ? 'Credential valid.' : 'Credential tidak valid.',
        ];
    }

    public function ensureSignerPrivateKeyExists(PolymarketAccount $account): string
    {
        return $this->secretResolverService->resolvePrivateKey($account->env_key_name);
    }
}
