<?php

namespace Database\Factories;

use App\Models\Market;
use App\Models\Order;
use App\Models\PolymarketAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position_id' => null,
            'market_id' => Market::factory(),
            'polymarket_account_id' => PolymarketAccount::factory(),
            'condition_id' => '0x'.$this->faker->regexify('[a-f0-9]{64}'),
            'token_id' => (string) $this->faker->numberBetween(100000, 999999999),
            'side' => $this->faker->randomElement(['YES', 'NO']),
            'order_type' => 'GTC',
            'price' => $this->faker->randomFloat(4, 0.01, 0.99),
            'size' => $this->faker->randomFloat(6, 1, 500),
            'filled_size' => 0,
            'status' => 'pending',
            'polymarket_order_id' => null,
            'client_order_id' => null,
            'idempotency_key' => null,
            'signature_type' => 0,
            'funder_address' => '0x'.$this->faker->regexify('[a-f0-9]{40}'),
            'tx_hash' => null,
            'raw_request' => null,
            'raw_response' => null,
        ];
    }
}
