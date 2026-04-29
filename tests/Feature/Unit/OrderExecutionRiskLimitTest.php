<?php

namespace Tests\Feature\Unit;

use App\Models\Order;
use App\Models\PolymarketAccount;
use App\Models\Position;
use App\Services\OrderExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderExecutionRiskLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_order_when_max_open_positions_limit_is_reached(): void
    {
        $account = PolymarketAccount::factory()->create([
            'is_active' => true,
            'wallet_address' => '0xabc123',
            'credential_status' => 'active',
            'max_open_positions' => 1,
        ]);

        $order = Order::factory()->create([
            'polymarket_account_id' => $account->id,
            'condition_id' => 'cond-1',
            'token_id' => '1001',
            'status' => 'submitted',
        ]);

        Position::query()->create([
            'market_id' => 'cond-1',
            'condition_id' => 'cond-1',
            'token_id' => '1001',
            'order_id' => $order->id,
            'side' => 'YES',
            'entry_price' => 0.5,
            'size' => 10,
            'status' => 'open',
        ]);

        $result = app(OrderExecutionService::class)->execute('cond-1', 'YES', 1, 0.5, [
            'account_id' => $account->id,
            'token_id' => '1001',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Order ditolak karena jumlah open position sudah mencapai batas account.',
            $result['error'] ?? null
        );
    }

    public function test_it_rejects_order_when_max_order_size_in_usd_is_exceeded(): void
    {
        $account = PolymarketAccount::factory()->create([
            'is_active' => true,
            'wallet_address' => '0xabc123',
            'credential_status' => 'active',
            'max_order_size_in_usd' => 50,
        ]);

        $result = app(OrderExecutionService::class)->execute('cond-2', 'YES', 200, 0.5, [
            'account_id' => $account->id,
            'token_id' => '2002',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Order melebihi batas max_order_size_in_usd account.',
            $result['error'] ?? null
        );
    }

    public function test_it_rejects_order_when_daily_loss_count_limit_is_reached(): void
    {
        $account = PolymarketAccount::factory()->create([
            'is_active' => true,
            'wallet_address' => '0xabc123',
            'credential_status' => 'active',
            'daily_limit_mode' => 'count',
            'max_daily_loss_position' => 1,
        ]);

        $order = Order::factory()->create([
            'polymarket_account_id' => $account->id,
            'condition_id' => 'cond-3',
            'token_id' => '3003',
            'status' => 'filled',
        ]);

        Position::query()->create([
            'market_id' => 'cond-3',
            'condition_id' => 'cond-3',
            'token_id' => '3003',
            'order_id' => $order->id,
            'side' => 'YES',
            'entry_price' => 0.5,
            'size' => 10,
            'status' => 'closed',
            'closed_at' => now(),
            'closed_pnl_usd' => -15.50,
            'outcome' => 'loss',
        ]);

        $result = app(OrderExecutionService::class)->execute('cond-3', 'YES', 1, 0.5, [
            'account_id' => $account->id,
            'token_id' => '3003',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Order ditolak karena limit harian jumlah posisi loss account sudah tercapai.',
            $result['error'] ?? null
        );
    }

    public function test_it_rejects_order_when_daily_loss_usd_limit_is_reached(): void
    {
        $account = PolymarketAccount::factory()->create([
            'is_active' => true,
            'wallet_address' => '0xabc123',
            'credential_status' => 'active',
            'daily_limit_mode' => 'usd',
            'max_daily_loss_position' => 50,
        ]);

        $order = Order::factory()->create([
            'polymarket_account_id' => $account->id,
            'condition_id' => 'cond-4',
            'token_id' => '4004',
            'status' => 'filled',
        ]);

        Position::query()->create([
            'market_id' => 'cond-4',
            'condition_id' => 'cond-4',
            'token_id' => '4004',
            'order_id' => $order->id,
            'side' => 'NO',
            'entry_price' => 0.6,
            'size' => 12,
            'status' => 'closed',
            'closed_at' => now(),
            'closed_pnl_usd' => -75.00,
            'outcome' => 'loss',
        ]);

        $result = app(OrderExecutionService::class)->execute('cond-4', 'NO', 1, 0.4, [
            'account_id' => $account->id,
            'token_id' => '4004',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(
            'Order ditolak karena limit harian loss (USD) account sudah tercapai.',
            $result['error'] ?? null
        );
    }
}
