<?php

namespace Database\Factories;

use App\Models\PolymarketAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolymarketAccount>
 */
class PolymarketAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => ucwords($name),
            'account_slug' => fake()->unique()->slug(),
            'wallet_address' => '0x'.fake()->regexify('[A-Fa-f0-9]{40}'),
            'funder_address' => '0x'.fake()->regexify('[A-Fa-f0-9]{40}'),
            'signature_type' => fake()->randomElement([0, 1, 2]),
            'env_key_name' => fake()->optional()->randomElement(['POLY_SIGNER_MAIN', 'POLY_SIGNER_ALPHA', 'POLY_SIGNER_BETA']),
            'vault_key_ref' => null,
            'api_key' => 'pk_'.fake()->bothify('????####'),
            'api_secret' => base64_encode(fake()->sha256()),
            'api_passphrase' => fake()->password(minLength: 12),
            'credential_status' => fake()->randomElement(['pending', 'active', 'needs_rotation']),
            'last_error_code' => null,
            'is_active' => true,
            'last_validated_at' => now(),
        ];
    }
}
