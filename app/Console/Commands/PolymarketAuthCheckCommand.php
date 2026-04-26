<?php

namespace App\Console\Commands;

use App\Services\Polymarket\PolymarketAuthService;
use Illuminate\Console\Command;

class PolymarketAuthCheckCommand extends Command
{
    protected $signature = 'polymarket:auth-check';

    protected $description = 'Validate Polymarket L2 API credentials';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketAuthService $authService): int
    {
        $result = $authService->validateL2Credentials();

        $this->line('Status: '.$result['status']);
        $this->line('Body: '.json_encode($result['body'], JSON_PRETTY_PRINT));

        if (! $result['ok']) {
            $this->error('Credential check gagal.');

            return self::FAILURE;
        }

        $this->info('Credential check berhasil.');

        return self::SUCCESS;
    }
}
