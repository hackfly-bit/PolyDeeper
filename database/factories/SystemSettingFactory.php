<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'polymarket.test.'.$this->faker->unique()->slug(),
            'value' => $this->faker->word(),
            'is_encrypted' => false,
        ];
    }
}
