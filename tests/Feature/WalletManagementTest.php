<?php

namespace Tests\Feature;

use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-26 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_can_create_wallet_and_sync_stats_from_polymarket(): void
    {
        $this->fakePolymarketStats(
            trades: [
                [
                    'side' => 'BUY',
                    'asset' => 'asset-a',
                    'size' => 10,
                    'price' => 0.4,
                    'usdcSize' => 4,
                    'timestamp' => 1700000000,
                ],
                [
                    'side' => 'SELL',
                    'asset' => 'asset-a',
                    'size' => 10,
                    'price' => 0.6,
                    'usdcSize' => 6,
                    'timestamp' => 1700000100,
                ],
                [
                    'side' => 'BUY',
                    'asset' => 'asset-b',
                    'size' => 5,
                    'price' => 0.6,
                    'usdcSize' => 3,
                    'timestamp' => 1700000200,
                ],
            ],
            activity: [
                ['timestamp' => 1700000300],
            ],
            value: [
                ['user' => '0xabcdef1234567890', 'value' => 2],
            ],
        );

        $response = $this->post(route('wallets.store'), [
            'name' => 'Wallet Alpha',
            'address' => 'ABCDEF1234567890',
        ]);

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHas('wallet_success');

        $wallet = Wallet::query()->firstOrFail();

        $this->assertSame('Wallet Alpha', $wallet->name);
        $this->assertSame('0xabcdef1234567890', $wallet->address);
        $this->assertEqualsWithDelta(1.00, $wallet->pnl, 0.001);
        $this->assertEqualsWithDelta(14.29, $wallet->roi, 0.001);
        $this->assertEqualsWithDelta(100.00, $wallet->win_rate, 0.001);
        $this->assertEqualsWithDelta(0.7114, $wallet->weight, 0.0001);
        $this->assertSame('2023-11-14 22:18:20', $wallet->last_active?->format('Y-m-d H:i:s'));
    }

    public function test_it_can_update_wallet_and_resync_stats(): void
    {
        $wallet = Wallet::query()->create([
            'name' => 'Old Wallet',
            'address' => '0x1111111111111111',
            'weight' => 0.5,
            'pnl' => 0,
            'win_rate' => 0,
            'roi' => 0,
            'last_active' => null,
        ]);

        $this->fakePolymarketStats(
            trades: [
                [
                    'side' => 'BUY',
                    'asset' => 'asset-c',
                    'size' => 20,
                    'price' => 0.5,
                    'usdcSize' => 10,
                    'timestamp' => 1701000000,
                ],
                [
                    'side' => 'SELL',
                    'asset' => 'asset-c',
                    'size' => 20,
                    'price' => 0.7,
                    'usdcSize' => 14,
                    'timestamp' => 1701000300,
                ],
            ],
            activity: [
                ['timestamp' => 1701000600],
            ],
            value: [
                ['user' => '0x2222222222222222', 'value' => 0],
            ],
        );

        $response = $this->put(route('wallets.update', $wallet), [
            'name' => 'Wallet Beta',
            'address' => '2222222222222222',
        ]);

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHas('wallet_success');

        $wallet->refresh();

        $this->assertSame('Wallet Beta', $wallet->name);
        $this->assertSame('0x2222222222222222', $wallet->address);
        $this->assertEqualsWithDelta(4.00, $wallet->pnl, 0.001);
        $this->assertEqualsWithDelta(40.00, $wallet->roi, 0.001);
        $this->assertEqualsWithDelta(100.00, $wallet->win_rate, 0.001);
    }

    public function test_it_can_refresh_wallet_stats_from_polymarket(): void
    {
        $wallet = Wallet::query()->create([
            'name' => 'Wallet Gamma',
            'address' => '0x3333333333333333',
            'weight' => 0.2,
            'pnl' => -10,
            'win_rate' => 20,
            'roi' => -50,
            'last_active' => Carbon::parse('2024-01-01 00:00:00'),
        ]);

        $this->fakePolymarketStats(
            trades: [
                [
                    'side' => 'BUY',
                    'asset' => 'asset-d',
                    'size' => 10,
                    'price' => 0.4,
                    'usdcSize' => 4,
                    'timestamp' => 1710000000,
                ],
                [
                    'side' => 'SELL',
                    'asset' => 'asset-d',
                    'size' => 10,
                    'price' => 0.8,
                    'usdcSize' => 8,
                    'timestamp' => 1710000300,
                ],
            ],
            activity: [
                ['timestamp' => 1710000400],
            ],
            value: [
                ['user' => '0x3333333333333333', 'value' => 1],
            ],
        );

        $response = $this->post(route('wallets.refresh', $wallet));

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHas('wallet_success');

        $wallet->refresh();

        $this->assertEqualsWithDelta(5.00, $wallet->pnl, 0.001);
        $this->assertEqualsWithDelta(125.00, $wallet->roi, 0.001);
        $this->assertEqualsWithDelta(0.84, $wallet->weight, 0.0001);
    }

    public function test_win_rate_uses_realized_gain_and_loss_from_closed_positions_only(): void
    {
        $this->fakePolymarketStats(
            trades: [
                [
                    'side' => 'BUY',
                    'asset' => 'asset-win',
                    'size' => 10,
                    'price' => 0.4,
                    'usdcSize' => 4,
                    'timestamp' => 1700000000,
                ],
                [
                    'side' => 'SELL',
                    'asset' => 'asset-win',
                    'size' => 5,
                    'price' => 0.6,
                    'usdcSize' => 3,
                    'timestamp' => 1700000100,
                ],
                [
                    'side' => 'BUY',
                    'asset' => 'asset-loss',
                    'size' => 10,
                    'price' => 0.7,
                    'usdcSize' => 7,
                    'timestamp' => 1700000200,
                ],
                [
                    'side' => 'SELL',
                    'asset' => 'asset-loss',
                    'size' => 10,
                    'price' => 0.5,
                    'usdcSize' => 5,
                    'timestamp' => 1700000300,
                ],
                [
                    'side' => 'BUY',
                    'asset' => 'asset-open',
                    'size' => 10,
                    'price' => 0.2,
                    'usdcSize' => 2,
                    'timestamp' => 1700000400,
                ],
            ],
            activity: [
                ['timestamp' => 1700000500],
            ],
            value: [
                ['user' => '0x4444444444444444', 'value' => 1],
            ],
        );

        $response = $this->post(route('wallets.store'), [
            'name' => 'Wallet Delta',
            'address' => '4444444444444444',
        ]);

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHas('wallet_success');

        $wallet = Wallet::query()->where('address', '0x4444444444444444')->firstOrFail();

        $this->assertEqualsWithDelta(-4.00, $wallet->pnl, 0.001);
        $this->assertEqualsWithDelta(-30.77, $wallet->roi, 0.001);
        $this->assertEqualsWithDelta(33.33, $wallet->win_rate, 0.001);
        $this->assertEqualsWithDelta(0.3105, $wallet->weight, 0.0001);
    }

    public function test_it_rejects_duplicate_wallet_after_address_normalization(): void
    {
        Wallet::query()->create([
            'name' => 'Existing Wallet',
            'address' => '0xabcdef1234567890',
            'weight' => 0.5,
            'pnl' => 0,
            'win_rate' => 0,
            'roi' => 0,
            'last_active' => null,
        ]);

        $response = $this->from(route('wallets'))->post(route('wallets.store'), [
            'name' => 'Duplicate Wallet',
            'address' => 'ABCDEF1234567890',
        ]);

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHasErrors('address');
        $this->assertSame(1, Wallet::query()->count());
    }

    public function test_it_returns_error_when_polymarket_sync_fails(): void
    {
        Http::fake([
            'https://data-api.polymarket.com/*' => Http::response(['message' => 'upstream error'], 500),
        ]);

        $response = $this->from(route('wallets'))->post(route('wallets.store'), [
            'name' => 'Wallet Error',
            'address' => '0x9999999999999999',
        ]);

        $response->assertRedirect(route('wallets'));
        $response->assertSessionHasErrors('wallet_sync');
        $this->assertDatabaseCount('wallets', 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $trades
     * @param  array<int, array<string, mixed>>  $activity
     * @param  array<int, array<string, mixed>>  $value
     */
    private function fakePolymarketStats(array $trades, array $activity, array $value): void
    {
        Http::fake([
            'https://data-api.polymarket.com/trades*' => Http::response($trades, 200),
            'https://data-api.polymarket.com/activity*' => Http::response($activity, 200),
            'https://data-api.polymarket.com/value*' => Http::response($value, 200),
        ]);
    }
}
