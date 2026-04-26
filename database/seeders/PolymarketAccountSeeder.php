<?php

namespace Database\Seeders;

use App\Models\PolymarketAccount;
use Illuminate\Database\Seeder;

class PolymarketAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PolymarketAccount::factory()->create([
            'name' => 'Primary Trader',
            'account_slug' => 'primary-trader',
            'credential_status' => 'active',
            'env_key_name' => 'POLY_SIGNER_PRIMARY',
        ]);
    }
}
