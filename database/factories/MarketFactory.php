<?php

namespace Database\Factories;

use App\Models\Market;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Market>
 */
class MarketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'condition_id' => '0x'.$this->faker->unique()->regexify('[a-f0-9]{64}'),
            'slug' => $this->faker->unique()->slug(),
            'question' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'active' => true,
            'closed' => false,
            'end_date' => now()->addDays(rand(1, 60)),
            'minimum_tick_size' => 0.01,
            'neg_risk' => false,
            'raw_payload' => null,
            'last_synced_at' => now(),
        ];
    }
}
