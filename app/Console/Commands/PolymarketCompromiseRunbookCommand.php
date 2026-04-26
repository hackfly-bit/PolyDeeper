<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PolymarketCompromiseRunbookCommand extends Command
{
    protected $signature = 'polymarket:compromise-runbook';

    protected $description = 'Tampilkan runbook insiden wallet compromise untuk operasi Polymarket';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('RUNBOOK WALLET COMPROMISE');
        $this->line('1) Aktifkan kill switch account: disable trading per account.');
        $this->line('2) Revoke credential L2 account terdampak.');
        $this->line('3) Putar signer env_key_name/vault_key_ref dan simpan credential baru.');
        $this->line('4) Validasi credential baru lalu monitor error 401/403 minimal 15 menit.');
        $this->line('5) Audit ulang order/account log untuk scope insiden.');

        return self::SUCCESS;
    }
}
