<?php

namespace App\Services\Polymarket;

use GMP;
use RuntimeException;

class Secp256k1Signer
{
    private GMP $curvePrime;

    private GMP $curveOrder;

    private GMP $generatorX;

    private GMP $generatorY;

    public function __construct()
    {
        $this->curvePrime = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F');
        $this->curveOrder = gmp_init('0xFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141');
        $this->generatorX = gmp_init('0x79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798');
        $this->generatorY = gmp_init('0x483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8');
    }

    public function addressFromPrivateKey(string $privateKey): string
    {
        $privateKeyInt = $this->normalizePrivateKey($privateKey);
        $publicKey = $this->multiplyPoint($this->generatorPoint(), $privateKeyInt);

        if ($publicKey === null) {
            throw new RuntimeException('Gagal membentuk public key dari private key signer.');
        }

        $publicKeyBytes = hex2bin(
            $this->padHex(gmp_strval($publicKey['x'], 16), 32)
            .$this->padHex(gmp_strval($publicKey['y'], 16), 32)
        );

        if ($publicKeyBytes === false) {
            throw new RuntimeException('Gagal mengubah public key signer ke format binary.');
        }

        return '0x'.substr(\kornrunner\Keccak::hash($publicKeyBytes, 256), -40);
    }

    public function signDigest(string $digestHex, string $privateKey): string
    {
        $normalizedDigest = $this->normalizeHex($digestHex);

        if (strlen($normalizedDigest) !== 64) {
            throw new RuntimeException('Digest ECDSA secp256k1 harus berupa 32 byte hex.');
        }

        $privateKeyInt = $this->normalizePrivateKey($privateKey);
        $digestInt = $this->mod(gmp_init('0x'.$normalizedDigest), $this->curveOrder);
        $nonce = $this->deterministicNonce($privateKeyInt, $normalizedDigest);
        $ephemeralPoint = $this->multiplyPoint($this->generatorPoint(), $nonce);

        if ($ephemeralPoint === null) {
            throw new RuntimeException('Gagal membentuk ephemeral point untuk signature secp256k1.');
        }

        $r = $this->mod($ephemeralPoint['x'], $this->curveOrder);
        if (gmp_cmp($r, 0) === 0) {
            throw new RuntimeException('Nilai r untuk signature secp256k1 tidak valid.');
        }

        $nonceInverse = gmp_invert($nonce, $this->curveOrder);
        if ($nonceInverse === false) {
            throw new RuntimeException('Gagal menghitung invers nonce untuk signature secp256k1.');
        }

        $s = $this->mod(
            gmp_mul(
                $nonceInverse,
                gmp_add($digestInt, gmp_mul($r, $privateKeyInt))
            ),
            $this->curveOrder
        );

        if (gmp_cmp($s, 0) === 0) {
            throw new RuntimeException('Nilai s untuk signature secp256k1 tidak valid.');
        }

        $recoveryId = $this->isOdd($ephemeralPoint['y']) ? 1 : 0;
        $halfOrder = gmp_div_q($this->curveOrder, 2);

        if (gmp_cmp($s, $halfOrder) > 0) {
            $s = gmp_sub($this->curveOrder, $s);
            $recoveryId = $recoveryId === 1 ? 0 : 1;
        }

        $v = dechex(27 + $recoveryId);

        return '0x'
            .$this->padHex(gmp_strval($r, 16), 32)
            .$this->padHex(gmp_strval($s, 16), 32)
            .$this->padHex($v, 1);
    }

    /**
     * @return array{x:GMP,y:GMP}
     */
    private function generatorPoint(): array
    {
        return [
            'x' => $this->generatorX,
            'y' => $this->generatorY,
        ];
    }

    private function normalizePrivateKey(string $privateKey): GMP
    {
        $normalizedKey = $this->normalizeHex($privateKey);

        if ($normalizedKey === '') {
            throw new RuntimeException('Private key signer tidak boleh kosong.');
        }

        if (! ctype_xdigit($normalizedKey)) {
            throw new RuntimeException('Format private key signer tidak valid.');
        }

        $privateKeyInt = gmp_init('0x'.$normalizedKey);

        if (
            gmp_cmp($privateKeyInt, 1) < 0
            || gmp_cmp($privateKeyInt, gmp_sub($this->curveOrder, 1)) > 0
        ) {
            throw new RuntimeException('Private key signer berada di luar rentang secp256k1 yang valid.');
        }

        return $privateKeyInt;
    }

    private function deterministicNonce(GMP $privateKey, string $digestHex): GMP
    {
        $privateKeyBytes = hex2bin($this->padHex(gmp_strval($privateKey, 16), 32));
        $digestBytes = hex2bin($this->padHex($digestHex, 32));

        if ($privateKeyBytes === false || $digestBytes === false) {
            throw new RuntimeException('Gagal menyiapkan input nonce deterministik secp256k1.');
        }

        $value = str_repeat("\x01", 32);
        $key = str_repeat("\x00", 32);
        $digestOctets = $this->bitsToOctets($digestBytes);

        $key = hash_hmac('sha256', $value."\x00".$privateKeyBytes.$digestOctets, $key, true);
        $value = hash_hmac('sha256', $value, $key, true);
        $key = hash_hmac('sha256', $value."\x01".$privateKeyBytes.$digestOctets, $key, true);
        $value = hash_hmac('sha256', $value, $key, true);

        while (true) {
            $candidate = '';

            while (strlen($candidate) < 32) {
                $value = hash_hmac('sha256', $value, $key, true);
                $candidate .= $value;
            }

            $nonce = gmp_init('0x'.bin2hex(substr($candidate, 0, 32)));

            if (gmp_cmp($nonce, 1) >= 0 && gmp_cmp($nonce, $this->curveOrder) < 0) {
                return $nonce;
            }

            $key = hash_hmac('sha256', $value."\x00", $key, true);
            $value = hash_hmac('sha256', $value, $key, true);
        }
    }

    private function bitsToOctets(string $digestBytes): string
    {
        $digestInt = gmp_init('0x'.bin2hex($digestBytes));
        $reduced = $this->mod($digestInt, $this->curveOrder);

        $octets = hex2bin($this->padHex(gmp_strval($reduced, 16), 32));

        if ($octets === false) {
            throw new RuntimeException('Gagal mengubah digest ke octet secp256k1.');
        }

        return $octets;
    }

    /**
     * @param  array{x:GMP,y:GMP}|null  $left
     * @param  array{x:GMP,y:GMP}|null  $right
     * @return array{x:GMP,y:GMP}|null
     */
    private function addPoints(?array $left, ?array $right): ?array
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        if (gmp_cmp($left['x'], $right['x']) === 0) {
            if (gmp_cmp($this->mod(gmp_add($left['y'], $right['y']), $this->curvePrime), 0) === 0) {
                return null;
            }

            $numerator = gmp_mul(3, gmp_pow($left['x'], 2));
            $denominator = gmp_mul(2, $left['y']);
        } else {
            $numerator = gmp_sub($right['y'], $left['y']);
            $denominator = gmp_sub($right['x'], $left['x']);
        }

        $inverse = gmp_invert($this->mod($denominator, $this->curvePrime), $this->curvePrime);
        if ($inverse === false) {
            throw new RuntimeException('Gagal menghitung invers affine point secp256k1.');
        }

        $lambda = $this->mod(gmp_mul($numerator, $inverse), $this->curvePrime);
        $x = $this->mod(
            gmp_sub(
                gmp_sub(gmp_pow($lambda, 2), $left['x']),
                $right['x']
            ),
            $this->curvePrime
        );
        $y = $this->mod(
            gmp_sub(
                gmp_mul($lambda, gmp_sub($left['x'], $x)),
                $left['y']
            ),
            $this->curvePrime
        );

        return ['x' => $x, 'y' => $y];
    }

    /**
     * @param  array{x:GMP,y:GMP}  $point
     * @return array{x:GMP,y:GMP}|null
     */
    private function multiplyPoint(array $point, GMP $scalar): ?array
    {
        $result = null;
        $addend = $point;
        $remaining = gmp_init(gmp_strval($scalar, 10), 10);

        while (gmp_cmp($remaining, 0) > 0) {
            if (gmp_testbit($remaining, 0)) {
                $result = $this->addPoints($result, $addend);
            }

            $addend = $this->addPoints($addend, $addend);
            $remaining = gmp_div_q($remaining, 2);
        }

        return $result;
    }

    private function mod(GMP $value, GMP $modulus): GMP
    {
        $result = gmp_mod($value, $modulus);

        if (gmp_cmp($result, 0) < 0) {
            return gmp_add($result, $modulus);
        }

        return $result;
    }

    private function isOdd(GMP $value): bool
    {
        return gmp_intval(gmp_mod($value, 2)) === 1;
    }

    private function normalizeHex(string $value): string
    {
        $normalized = strtolower(trim($value));

        if (str_starts_with($normalized, '0x')) {
            $normalized = substr($normalized, 2);
        }

        return $normalized;
    }

    private function padHex(string $hex, int $bytes): string
    {
        return str_pad(strtolower($hex), $bytes * 2, '0', STR_PAD_LEFT);
    }
}
