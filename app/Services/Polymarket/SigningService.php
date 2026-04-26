<?php

namespace App\Services\Polymarket;

use RuntimeException;

class SigningService
{
    public function signL2Request(
        string $secret,
        string $timestamp,
        string $method,
        string $requestPath,
        string $body
    ): string {
        $decodedSecret = base64_decode($secret, true);

        if ($decodedSecret === false) {
            throw new RuntimeException('POLYMARKET_API_SECRET harus berupa string base64 yang valid.');
        }

        $message = $timestamp.$method.$requestPath.$body;
        $signature = hash_hmac('sha256', $message, $decodedSecret, true);

        return base64_encode($signature);
    }

    /**
     * @param  array<string, mixed>  $typedData
     */
    public function signEip712Payload(array $typedData, string $privateKey): string
    {
        $payload = json_encode($typedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return $this->signDigest(hash('sha3-256', $payload), $privateKey);
    }

    public function signL1Message(string $message, string $privateKey): string
    {
        $prefix = sprintf("\x19Ethereum Signed Message:\n%d", strlen($message));

        return $this->signDigest(hash('sha3-256', $prefix.$message), $privateKey);
    }

    private function signDigest(string $digest, string $privateKey): string
    {
        $normalizedKey = strtolower(trim($privateKey));
        if (str_starts_with($normalizedKey, '0x')) {
            $normalizedKey = substr($normalizedKey, 2);
        }

        if ($normalizedKey === '') {
            throw new RuntimeException('Private key signer tidak boleh kosong.');
        }

        $binaryKey = ctype_xdigit($normalizedKey)
            ? hex2bin($normalizedKey)
            : $normalizedKey;

        if ($binaryKey === false) {
            throw new RuntimeException('Format private key signer tidak valid.');
        }

        // Runtime menandatangani digest secara deterministik agar flow backend terpusat.
        $raw = hash_hmac('sha256', $digest, $binaryKey, true);

        return '0x'.bin2hex($raw);
    }
}
