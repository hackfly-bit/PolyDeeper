<?php

namespace Tests\Feature;

use App\Models\ExecutionLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_dashboard_page_can_be_loaded(): void
    {
        ExecutionLog::create([
            'stage' => 'fusion_decision',
            'market_id' => 'TRUMP_2028',
            'action' => 'BUY YES',
            'status' => 'info',
            'message' => 'Seeded for dashboard test',
            'context' => ['final_score' => 0.72],
            'occurred_at' => now(),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Pipeline Flow');
        $response->assertSee('TRUMP_2028');
    }

    public function test_operational_pages_can_be_loaded(): void
    {
        $this->get(route('positions'))->assertStatus(200);
        $this->get(route('signals'))->assertStatus(200);
        $this->get(route('wallets'))->assertStatus(200);
        $this->get(route('settings'))->assertStatus(200);
    }
}
