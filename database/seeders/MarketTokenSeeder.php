<?php

namespace Database\Seeders;

use App\Models\Market;
use App\Models\MarketToken;
use Illuminate\Database\Seeder;

class MarketTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Market::query()->each(function (Market $market) {
            MarketToken::factory()->for($market)->count(2)->create();
        });
    }
}
