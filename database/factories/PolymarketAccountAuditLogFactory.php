<?php

namespace Database\Factories;

use App\Models\PolymarketAccount;
use App\Models\PolymarketAccountAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PolymarketAccountAuditLog>
 */
class PolymarketAccountAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'polymarket_account_id' => PolymarketAccount::factory(),
            'action' => fake()->randomElement([
                'credential.rotate',
                'credential.revoke',
                'credential.validate',
                'trading.disable',
            ]),
            'status' => fake()->randomElement(['info', 'success', 'warning', 'error']),
            'actor' => 'system',
            'message' => fake()->sentence(),
            'context' => ['source' => 'factory'],
            'occurred_at' => now(),
        ];
    }
}
