<?php

namespace Database\Factories;

use App\Models\Market;
use App\Models\MarketToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketToken>
 */
class MarketTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $outcome = $this->faker->randomElement(['YES', 'NO']);

        return [
            'market_id' => Market::factory(),
            'token_id' => (string) $this->faker->unique()->numberBetween(100000, 999999999),
            'outcome' => $outcome,
            'is_yes' => $outcome === 'YES',
            'raw_payload' => null,
        ];
    }
}
