<?php

namespace App\Services\Polymarket;

use GMP;
use kornrunner\Keccak;
use RuntimeException;

class SigningService
{
    public function __construct(public Secp256k1Signer $secp256k1Signer) {}

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
        $digest = $this->hashTypedData($typedData);

        if ($digest === null) {
            $payload = json_encode($typedData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $digest = Keccak::hash($payload, 256);
        }

        return $this->signDigest($digest, $privateKey);
    }

    public function signClobAuthMessage(
        string $address,
        string $timestamp,
        int $nonce,
        string $privateKey
    ): string {
        return $this->signEip712Payload([
            'domain' => [
                'name' => 'ClobAuthDomain',
                'version' => '1',
                'chainId' => 137,
            ],
            'types' => [
                'ClobAuth' => [
                    ['name' => 'address', 'type' => 'address'],
                    ['name' => 'timestamp', 'type' => 'string'],
                    ['name' => 'nonce', 'type' => 'uint256'],
                    ['name' => 'message', 'type' => 'string'],
                ],
            ],
            'primaryType' => 'ClobAuth',
            'message' => [
                'address' => $address,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
                'message' => 'This message attests that I control the given wallet',
            ],
        ], $privateKey);
    }

    public function signL1Message(string $message, string $privateKey): string
    {
        $prefix = sprintf("\x19Ethereum Signed Message:\n%d", strlen($message));

        return $this->signDigest(Keccak::hash($prefix.$message, 256), $privateKey);
    }

    public function addressFromPrivateKey(string $privateKey): string
    {
        return $this->secp256k1Signer->addressFromPrivateKey($privateKey);
    }

    private function signDigest(string $digest, string $privateKey): string
    {
        return $this->secp256k1Signer->signDigest($digest, $privateKey);
    }

    /**
     * @param  array<string, mixed>  $typedData
     */
    private function hashTypedData(array $typedData): ?string
    {
        $domain = $typedData['domain'] ?? null;
        $message = $typedData['message'] ?? null;
        $primaryType = $typedData['primaryType'] ?? null;
        $types = $typedData['types'] ?? null;

        if (! is_array($domain) || ! is_array($message) || ! is_array($types) || ! is_string($primaryType) || $primaryType === '') {
            return null;
        }

        $domainType = $this->buildDomainTypeDefinition($domain);

        if ($domainType === null) {
            return null;
        }

        $augmentedTypes = $types;
        $augmentedTypes['EIP712Domain'] = $domainType;

        try {
            $domainSeparator = $this->hashStruct('EIP712Domain', $domain, $augmentedTypes);
            $messageHash = $this->hashStruct($primaryType, $message, $augmentedTypes);
            $payload = hex2bin($domainSeparator).hex2bin($messageHash);
        } catch (RuntimeException) {
            return null;
        }

        if ($payload === false) {
            return null;
        }

        return Keccak::hash("\x19\x01".$payload, 256);
    }

    /**
     * @param  array<string, mixed>  $domain
     * @return array<int, array{name:string,type:string}>|null
     */
    private function buildDomainTypeDefinition(array $domain): ?array
    {
        $knownFields = [
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'version', 'type' => 'string'],
            ['name' => 'chainId', 'type' => 'uint256'],
            ['name' => 'verifyingContract', 'type' => 'address'],
            ['name' => 'salt', 'type' => 'bytes32'],
        ];
        $resolvedFields = [];

        foreach ($knownFields as $field) {
            if (array_key_exists($field['name'], $domain)) {
                $resolvedFields[] = $field;
            }
        }

        if ($resolvedFields === []) {
            return null;
        }

        $allowedNames = array_column($knownFields, 'name');

        foreach (array_keys($domain) as $fieldName) {
            if (! in_array($fieldName, $allowedNames, true)) {
                return null;
            }
        }

        return $resolvedFields;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     */
    private function hashStruct(string $primaryType, array $data, array $types): string
    {
        return Keccak::hash($this->encodeData($primaryType, $data, $types), 256);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     */
    private function encodeData(string $primaryType, array $data, array $types): string
    {
        if (! isset($types[$primaryType]) || ! is_array($types[$primaryType])) {
            throw new RuntimeException('Definisi type EIP-712 tidak ditemukan untuk `'.$primaryType.'`.');
        }

        $encoded = hex2bin($this->typeHash($primaryType, $types));

        if ($encoded === false) {
            throw new RuntimeException('Gagal menyiapkan type hash EIP-712.');
        }

        foreach ($types[$primaryType] as $field) {
            $fieldName = $field['name'] ?? null;
            $fieldType = $field['type'] ?? null;

            if (! is_string($fieldName) || ! is_string($fieldType) || ! array_key_exists($fieldName, $data)) {
                throw new RuntimeException('Payload EIP-712 tidak lengkap untuk `'.$primaryType.'`.');
            }

            $encoded .= $this->encodeValue($fieldType, $data[$fieldName], $types);
        }

        return $encoded;
    }

    /**
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     */
    private function typeHash(string $primaryType, array $types): string
    {
        return Keccak::hash($this->encodeType($primaryType, $types), 256);
    }

    /**
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     */
    private function encodeType(string $primaryType, array $types): string
    {
        $dependencies = $this->collectDependencies($primaryType, $types);
        sort($dependencies);

        $orderedTypes = array_merge([$primaryType], $dependencies);
        $encoded = '';

        foreach ($orderedTypes as $typeName) {
            $fields = $types[$typeName] ?? null;

            if (! is_array($fields)) {
                throw new RuntimeException('Definisi fields EIP-712 tidak ditemukan untuk `'.$typeName.'`.');
            }

            $signature = [];

            foreach ($fields as $field) {
                $fieldName = $field['name'] ?? null;
                $fieldType = $field['type'] ?? null;

                if (! is_string($fieldName) || ! is_string($fieldType)) {
                    throw new RuntimeException('Field EIP-712 tidak valid untuk `'.$typeName.'`.');
                }

                $signature[] = $fieldType.' '.$fieldName;
            }

            $encoded .= $typeName.'('.implode(',', $signature).')';
        }

        return $encoded;
    }

    /**
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     * @return array<int, string>
     */
    private function collectDependencies(string $primaryType, array $types): array
    {
        $dependencies = [];
        $this->collectDependenciesRecursively($primaryType, $types, $dependencies);

        return array_values(array_filter(
            array_keys($dependencies),
            static fn (string $type): bool => $type !== $primaryType
        ));
    }

    /**
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     * @param  array<string, bool>  $dependencies
     */
    private function collectDependenciesRecursively(string $typeName, array $types, array &$dependencies): void
    {
        if (! isset($types[$typeName]) || isset($dependencies[$typeName])) {
            return;
        }

        $dependencies[$typeName] = true;

        foreach ($types[$typeName] as $field) {
            $fieldType = $field['type'] ?? null;

            if (! is_string($fieldType)) {
                throw new RuntimeException('Type field EIP-712 tidak valid untuk `'.$typeName.'`.');
            }

            $baseType = $this->baseType($fieldType);

            if (isset($types[$baseType])) {
                $this->collectDependenciesRecursively($baseType, $types, $dependencies);
            }
        }
    }

    /**
     * @param  array<string, array<int, array{name:string,type:string}>>  $types
     */
    private function encodeValue(string $type, mixed $value, array $types): string
    {
        $baseType = $this->baseType($type);

        if ($baseType !== $type) {
            throw new RuntimeException('Array EIP-712 belum didukung oleh implementation signer ini.');
        }

        if (isset($types[$type])) {
            if (! is_array($value)) {
                throw new RuntimeException('Nilai struct EIP-712 untuk `'.$type.'` harus berupa array.');
            }

            $hashedStruct = $this->hashStruct($type, $value, $types);
            $binaryStruct = hex2bin($hashedStruct);

            if ($binaryStruct === false) {
                throw new RuntimeException('Gagal mengubah struct hash EIP-712 ke binary.');
            }

            return $binaryStruct;
        }

        if ($type === 'string') {
            $binaryString = (string) $value;

            return $this->hexToBinary(Keccak::hash($binaryString, 256));
        }

        if ($type === 'bytes') {
            return $this->hexToBinary(Keccak::hash($this->binaryValue($value), 256));
        }

        if ($type === 'bool') {
            return $this->encodeUnsignedInteger($value ? 1 : 0);
        }

        if ($type === 'address') {
            return $this->hexToBinary($this->encodeAddress((string) $value));
        }

        if (preg_match('/^uint(?:8|16|24|32|40|48|56|64|72|80|88|96|104|112|120|128|136|144|152|160|168|176|184|192|200|208|216|224|232|240|248|256)?$/', $type) === 1) {
            return $this->hexToBinary($this->encodeUnsignedInteger($value));
        }

        if (preg_match('/^int(?:8|16|24|32|40|48|56|64|72|80|88|96|104|112|120|128|136|144|152|160|168|176|184|192|200|208|216|224|232|240|248|256)?$/', $type) === 1) {
            return $this->hexToBinary($this->encodeSignedInteger($value));
        }

        if (preg_match('/^bytes([1-9]|[12][0-9]|3[0-2])$/', $type, $matches) === 1) {
            return $this->hexToBinary($this->encodeFixedBytes($value, (int) $matches[1]));
        }

        throw new RuntimeException('Type EIP-712 `'.$type.'` belum didukung.');
    }

    private function encodeAddress(string $address): string
    {
        $normalizedAddress = $this->normalizeHex($address);

        if (strlen($normalizedAddress) !== 40 || ! ctype_xdigit($normalizedAddress)) {
            throw new RuntimeException('Alamat EVM untuk signature Polymarket tidak valid.');
        }

        return str_pad($normalizedAddress, 64, '0', STR_PAD_LEFT);
    }

    private function encodeUnsignedInteger(mixed $value): string
    {
        $integer = $this->normalizeInteger($value);

        if (gmp_cmp($integer, 0) < 0) {
            throw new RuntimeException('Nilai uint EIP-712 tidak boleh negatif.');
        }

        return str_pad(gmp_strval($integer, 16), 64, '0', STR_PAD_LEFT);
    }

    private function encodeSignedInteger(mixed $value): string
    {
        $integer = $this->normalizeInteger($value);

        if (gmp_cmp($integer, 0) >= 0) {
            return str_pad(gmp_strval($integer, 16), 64, '0', STR_PAD_LEFT);
        }

        $modulus = gmp_pow(2, 256);
        $encoded = gmp_add($modulus, $integer);

        return str_pad(gmp_strval($encoded, 16), 64, '0', STR_PAD_LEFT);
    }

    private function encodeFixedBytes(mixed $value, int $length): string
    {
        $hex = $this->normalizeHex((string) $value);

        if (strlen($hex) !== $length * 2 || ! ctype_xdigit($hex)) {
            throw new RuntimeException('Nilai `bytes'.$length.'` EIP-712 tidak valid.');
        }

        return str_pad($hex, 64, '0', STR_PAD_RIGHT);
    }

    private function normalizeInteger(mixed $value): GMP
    {
        $normalizedValue = is_int($value) ? (string) $value : trim((string) $value);

        if (! preg_match('/^-?\d+$/', $normalizedValue)) {
            throw new RuntimeException('Nilai integer EIP-712 tidak valid.');
        }

        return gmp_init($normalizedValue, 10);
    }

    private function baseType(string $type): string
    {
        return preg_replace('/\[[^\]]*\]$/', '', $type) ?? $type;
    }

    private function binaryValue(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Nilai bytes EIP-712 harus berupa string.');
        }

        $normalizedValue = $this->normalizeHex($value);

        if ($normalizedValue !== '' && strlen($normalizedValue) % 2 === 0 && ctype_xdigit($normalizedValue) && str_starts_with(strtolower(trim($value)), '0x')) {
            $binaryValue = hex2bin($normalizedValue);

            if ($binaryValue === false) {
                throw new RuntimeException('Nilai bytes hex EIP-712 tidak valid.');
            }

            return $binaryValue;
        }

        return $value;
    }

    private function hexToBinary(string $hex): string
    {
        $binary = hex2bin($hex);

        if ($binary === false) {
            throw new RuntimeException('Hex signer tidak dapat diubah ke binary.');
        }

        return $binary;
    }

    private function normalizeHex(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (str_starts_with($normalized, '0x')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized;
    }
}
