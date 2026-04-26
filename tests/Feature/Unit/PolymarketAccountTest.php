<?php

namespace Tests\Feature\Unit;

use App\Models\PolymarketAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PolymarketAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_encrypts_sensitive_credential_fields(): void
    {
        $account = PolymarketAccount::factory()->create([
            'api_secret' => 'super-secret-value',
            'api_passphrase' => 'super-passphrase',
        ]);

        $storedRow = DB::table('polymarket_accounts')->where('id', $account->id)->first();

        $this->assertNotNull($storedRow);
        $this->assertNotSame('super-secret-value', $storedRow->api_secret);
        $this->assertNotSame('super-passphrase', $storedRow->api_passphrase);

        $freshAccount = PolymarketAccount::query()->findOrFail($account->id);

        $this->assertSame('super-secret-value', $freshAccount->api_secret);
        $this->assertSame('super-passphrase', $freshAccount->api_passphrase);
    }
}
