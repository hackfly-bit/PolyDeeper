<?php

namespace Tests\Feature;

use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_active_widgets_and_reload_controls(): void
    {
        Wallet::query()->create([
            'name' => 'Wallet Dashboard',
            'address' => '0xaaaa111122223333',
            'weight' => 0.73,
            'pnl' => 12.5,
            'win_rate' => 66.67,
            'roi' => 18.9,
            'last_active' => Carbon::parse('2026-04-26 10:00:00'),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Dashboard Aktif');
        $response->assertSee('Reload Dashboard');
        $response->assertSee('Overview Status');
        $response->assertSee('Pipeline Flow');
        $response->assertSee('Runtime Monitor');
        $response->assertSee('Recent Signals & Executions', false);
        $response->assertSee('Tracked Wallet Performance');
        $response->assertSee('0xaaaa111122223333');
    }
}
