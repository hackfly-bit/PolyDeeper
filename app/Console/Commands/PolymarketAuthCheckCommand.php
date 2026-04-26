<?php

namespace App\Console\Commands;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketCredentialService;
use Illuminate\Console\Command;

class PolymarketAuthCheckCommand extends Command
{
    protected $signature = 'polymarket:auth-check {account : ID account Polymarket yang akan divalidasi}';

    protected $description = 'Validate account Polymarket dan bootstrap credential L2 bila belum tersedia';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketCredentialService $credentialService): int
    {
        $account = PolymarketAccount::query()->find($this->argument('account'));

        if (! $account instanceof PolymarketAccount) {
            $this->error('Account Polymarket tidak ditemukan.');

            return self::FAILURE;
        }

        $result = $credentialService->validateCredentials($account);

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
