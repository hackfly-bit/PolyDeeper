<?php

namespace Tests\Feature;

use App\Jobs\ProcessWalletTradeJob;
use App\Jobs\SyncMarketsJob;
use App\Jobs\SyncOpenOrdersJob;
use App\Models\Market;
use App\Models\PolymarketAccount;
use App\Models\Wallet;
use App\Services\Polymarket\PolymarketCredentialService;
use App\Services\Polymarket\PolymarketGammaService;
use App\Services\OrderSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PolymarketCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
    }

    public function test_sync_markets_command_runs_inline_and_reports_summary(): void
    {
        $this->mock(PolymarketGammaService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('syncActiveMarkets')
                ->once()
                ->with(50, 3, true)
                ->andReturn([
                    'inserted' => 2,
                    'updated' => 4,
                    'tokens_upserted' => 8,
                    'pages' => 3,
                ]);
        });

        $this->artisan('polymarket:sync-markets', [
            '--inline' => true,
            '--limit' => 50,
            '--max-pages' => 3,
            '--lock-seconds' => 60,
        ])
            ->expectsOutput('Sync markets selesai. scope=watched-only inserted=2 updated=4 tokens_upserted=8 pages=3')
            ->assertExitCode(0);
    }

    public function test_sync_markets_command_uses_queue_by_default(): void
    {
        Queue::fake();

        $this->artisan('polymarket:sync-markets', [
            '--limit' => 70,
            '--max-pages' => 4,
        ])
            ->expectsOutput('Dispatch sync markets diminta. scope=watched-only limit=70 max_pages=4')
            ->assertExitCode(0);

        Queue::assertPushed(SyncMarketsJob::class, function (SyncMarketsJob $job): bool {
            return $job->pageSize === 70 && $job->maxPages === 4 && $job->watchedOnly;
        });
    }

    public function test_sync_markets_command_can_disable_watched_only_mode(): void
    {
        Queue::fake();

        $this->artisan('polymarket:sync-markets', [
            '--limit' => 40,
            '--max-pages' => 2,
            '--all' => true,
        ])
            ->expectsOutput('Dispatch sync markets diminta. scope=all limit=40 max_pages=2')
            ->assertExitCode(0);

        Queue::assertPushed(SyncMarketsJob::class, function (SyncMarketsJob $job): bool {
            return $job->pageSize === 40 && $job->maxPages === 2 && ! $job->watchedOnly;
        });
    }

    public function test_sync_orders_command_queues_jobs_only_for_active_accounts(): void
    {
        Queue::fake();

        $activeAccount = PolymarketAccount::factory()->create([
            'name' => 'Alpha Account',
            'is_active' => true,
            'credential_status' => 'active',
        ]);
        PolymarketAccount::factory()->create([
            'name' => 'Dormant Account',
            'is_active' => false,
            'credential_status' => 'active',
        ]);

        $this->artisan('polymarket:sync-orders', [
            '--limit' => 75,
        ])
            ->expectsOutput(sprintf(
                'Queue sync order diminta untuk account #%d (%s).',
                $activeAccount->id,
                $activeAccount->name
            ))
            ->expectsOutput('Dispatch sync order diminta untuk 1 account aktif.')
            ->assertExitCode(0);

        Queue::assertPushed(SyncOpenOrdersJob::class, function (SyncOpenOrdersJob $job) use ($activeAccount): bool {
            return $job->accountId === $activeAccount->id && $job->limit === 75;
        });
        Queue::assertPushed(SyncOpenOrdersJob::class, 1);
    }

    public function test_sync_orders_command_inline_continues_when_one_account_fails(): void
    {
        $account = PolymarketAccount::factory()->create([
            'name' => 'Broken Account',
            'is_active' => true,
            'credential_status' => 'active',
        ]);

        $this->mock(OrderSyncService::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('syncOpenOrders')
                ->once()
                ->with(200, \Mockery::on(function (PolymarketAccount $subject) use ($account): bool {
                    return $subject->is($account);
                }))
                ->andThrow(new RuntimeException('Konfigurasi L2 Polymarket belum lengkap.'));
        });

        $this->artisan('polymarket:sync-orders', [
            '--inline' => true,
            '--account' => $account->id,
            '--lock-seconds' => 30,
        ])
            ->expectsOutput(sprintf(
                'Sync order gagal untuk account #%d (%s): Konfigurasi L2 Polymarket belum lengkap.',
                $account->id,
                $account->name
            ))
            ->expectsOutput('Sync order inline selesai. processed=0 skipped=0 failed=1 total_synced=0')
            ->assertExitCode(0);
    }

    public function test_listen_command_once_mode_dispatches_trades_and_updates_cursor(): void
    {
        Queue::fake();

        Wallet::query()->create([
            'name' => 'Listener Wallet',
            'address' => '0xabc123',
            'weight' => 1,
            'pnl' => 0,
            'win_rate' => 0,
            'roi' => 0,
            'last_active' => now(),
        ]);

        Redis::shouldReceive('get')
            ->once()
            ->with('polymarket:last_trade_timestamp')
            ->andReturn(100);
        Redis::shouldReceive('set')
            ->once()
            ->with('polymarket:last_trade_timestamp', 105)
            ->andReturn(true);

        Http::fake([
            'https://data-api.polymarket.com/trades*' => Http::response([
                [
                    'maker_address' => '0xabc123',
                    'condition_id' => 'condition-1',
                    'token_id' => 'token-1',
                    'side' => 'yes',
                    'price' => 0.45,
                    'size' => 12,
                    'transaction_hash' => '0xhash1',
                    'timestamp' => 101,
                ],
                [
                    'maker_address' => '0xabc123',
                    'condition_id' => 'condition-2',
                    'token_id' => 'token-2',
                    'side' => 'no',
                    'price' => 0.61,
                    'size' => 7,
                    'transaction_hash' => '0xhash2',
                    'timestamp' => 105,
                ],
            ], 200),
        ]);

        $this->artisan('polymarket:listen', [
            '--once' => true,
            '--sleep' => 0,
            '--limit' => 50,
            '--rewind' => 0,
            '--lock-seconds' => 20,
        ])
            ->expectsOutput('Polymarket listener dimulai. once=yes sleep=0 limit=50 lookback=30 rewind=0 max_loops=1')
            ->expectsOutput('Listener loop #1 selesai. wallets=1 fetched=2 dispatched=2 cursor=105')
            ->expectsOutput('Polymarket listener berhenti setelah 1 loop.')
            ->assertExitCode(0);

        Queue::assertPushed(ProcessWalletTradeJob::class, 2);
    }

    public function test_auth_check_command_displays_ui_aligned_account_summary_and_success_message(): void
    {
        $account = PolymarketAccount::factory()->create([
            'name' => 'Main Account',
            'wallet_address' => '0xabc123',
            'env_key_name' => 'POLY_SIGNER_MAIN',
            'signature_type' => 0,
            'credential_status' => 'active',
            'is_active' => true,
            'api_key' => 'pk_demo_12345678',
            'last_error_code' => null,
            'last_validated_at' => now(),
        ]);

        $this->mock(PolymarketCredentialService::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('ensureSignerPrivateKeyExists')
                ->once()
                ->withArgs(function (PolymarketAccount $subject) use ($account): bool {
                    return $subject->is($account);
                });

            $mock->shouldReceive('validateCredentials')
                ->once()
                ->withArgs(function (PolymarketAccount $subject) use ($account): bool {
                    return $subject->is($account);
                })
                ->andReturn([
                    'ok' => true,
                    'status' => 200,
                    'message' => 'Credential valid dan siap dipakai.',
                ]);
        });

        $this->artisan('polymarket:auth-check', [
            'account' => $account->id,
        ])
            ->expectsOutput('Polymarket Auth Check')
            ->expectsOutput(sprintf('Account: #%d %s', $account->id, $account->name))
            ->expectsOutput('Wallet: 0xabc123')
            ->expectsOutput('Env Key: POLY_SIGNER_MAIN')
            ->expectsOutput('Credential Status: Active')
            ->expectsOutput('HTTP Status: 200')
            ->expectsOutput('Message: Credential valid dan siap dipakai.')
            ->expectsOutput('Credential check berhasil.')
            ->assertExitCode(0);
    }

    public function test_auth_check_command_returns_failure_when_signer_key_missing(): void
    {
        $account = PolymarketAccount::factory()->create();

        $this->mock(PolymarketCredentialService::class, function (MockInterface $mock) use ($account): void {
            $mock->shouldReceive('ensureSignerPrivateKeyExists')
                ->once()
                ->withArgs(function (PolymarketAccount $subject) use ($account): bool {
                    return $subject->is($account);
                })
                ->andThrow(new RuntimeException('Secret env key tidak ditemukan.'));

            $mock->shouldNotReceive('validateCredentials');
        });

        $this->artisan('polymarket:auth-check', [
            'account' => $account->id,
        ])
            ->expectsOutput('Polymarket Auth Check')
            ->expectsOutput('Secret env key tidak ditemukan.')
            ->assertExitCode(1);
    }

    public function test_markers_page_merges_multiple_wallet_names_for_same_market(): void
    {
        $walletAlpha = Wallet::query()->create([
            'name' => 'Alpha',
            'address' => '0xalpha',
            'weight' => 1,
            'pnl' => 0,
            'win_rate' => 0,
            'roi' => 0,
            'last_active' => now(),
        ]);
        $walletBeta = Wallet::query()->create([
            'name' => 'Beta',
            'address' => '0xbeta',
            'weight' => 1,
            'pnl' => 0,
            'win_rate' => 0,
            'roi' => 0,
            'last_active' => now(),
        ]);

        $market = Market::factory()->create([
            'condition_id' => 'condition-merge',
            'slug' => 'will-eth-go-up-this-week',
            'question' => 'Will ETH go up this week?',
            'description' => 'Aturan market ETH',
            'raw_payload' => [
                'category' => 'Crypto',
                'volume' => 12345.67,
                'context' => 'Konteks market ETH',
            ],
            'end_date' => now()->addDays(2),
        ]);

        foreach ([$walletAlpha, $walletBeta] as $wallet) {
            \App\Models\WalletTrade::query()->create([
                'wallet_id' => $wallet->id,
                'market_ref_id' => $market->id,
                'market_id' => $market->condition_id,
                'condition_id' => $market->condition_id,
                'token_id' => 'token-'.$wallet->id,
                'side' => 'YES',
                'price' => 0.5,
                'size' => 10,
                'traded_at' => now(),
            ]);
        }

        $response = $this->get(route('markers'));

        $response->assertOk();
        $response->assertSee('Will ETH go up this week?');
        $response->assertSee('Alpha, Beta');
        $response->assertSee('Merged 2 wallet');
        $response->assertSee('Buka Polymarket');
    }
}
