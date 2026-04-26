<?php

namespace App\Services\Polymarket;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;

class PolymarketConfigService
{
    private const ADDRESS_KEY = 'polymarket.address';
    private const FUNDER_KEY = 'polymarket.funder';
    private const API_KEY = 'polymarket.api_key';
    private const API_SECRET = 'polymarket.api_secret';
    private const API_PASSPHRASE = 'polymarket.api_passphrase';
    private const SIGNATURE_TYPE = 'polymarket.signature_type';

    /**
     * @return array{
     *     address:?string,
     *     funder:?string,
     *     api_key:?string,
     *     api_secret:?string,
     *     api_passphrase:?string,
     *     signature_type:int
     * }
     */
    public function tradingConfig(): array
    {
        $signatureType = (int) ($this->nullableString($this->get(self::SIGNATURE_TYPE)) ?? '0');
        $funder = $this->nullableString($this->get(self::FUNDER_KEY));
        $address = $this->nullableString($this->get(self::ADDRESS_KEY));

        if ($address === null && $signatureType === 0) {
            $address = $funder;
        }

        return [
            'address' => $address,
            'funder' => $funder,
            'api_key' => $this->nullableString($this->get(self::API_KEY)),
            'api_secret' => $this->nullableString($this->get(self::API_SECRET)),
            'api_passphrase' => $this->nullableString($this->get(self::API_PASSPHRASE)),
            'signature_type' => $signatureType,
        ];
    }

    public function signatureType(): int
    {
        return $this->tradingConfig()['signature_type'];
    }

    public function funderAddress(): ?string
    {
        return $this->tradingConfig()['funder'];
    }

    public function signerAddress(): ?string
    {
        return $this->tradingConfig()['address'];
    }

    /**
     * @param  array{
     *     address?:?string,
     *     funder?:?string,
     *     api_key?:?string,
     *     api_secret?:?string,
     *     api_passphrase?:?string,
     *     signature_type?:int|string|null
     * }  $values
     */
    public function storeTradingConfig(array $values): void
    {
        $mapping = [
            self::ADDRESS_KEY => ['value' => $values['address'] ?? null, 'encrypted' => false],
            self::FUNDER_KEY => ['value' => $values['funder'] ?? null, 'encrypted' => false],
            self::API_KEY => ['value' => $values['api_key'] ?? null, 'encrypted' => false],
            self::API_SECRET => ['value' => $values['api_secret'] ?? null, 'encrypted' => true],
            self::API_PASSPHRASE => ['value' => $values['api_passphrase'] ?? null, 'encrypted' => true],
            self::SIGNATURE_TYPE => ['value' => $values['signature_type'] ?? null, 'encrypted' => false],
        ];

        foreach ($mapping as $key => $setting) {
            if (! array_key_exists('value', $setting) || $setting['value'] === null) {
                continue;
            }

            $plainValue = is_string($setting['value'])
                ? trim($setting['value'])
                : (string) $setting['value'];

            if ($plainValue === '') {
                SystemSetting::query()->where('key', $key)->delete();

                continue;
            }

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => $setting['encrypted'] ? Crypt::encryptString($plainValue) : $plainValue,
                    'is_encrypted' => $setting['encrypted'],
                ]
            );
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::query()->where('key', $key)->first();

        if ($setting === null || $setting->value === null || trim((string) $setting->value) === '') {
            return $default;
        }

        if ($setting->is_encrypted) {
            return Crypt::decryptString($setting->value);
        }

        return $setting->value;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }
}
