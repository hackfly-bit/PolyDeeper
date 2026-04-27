<?php

namespace App\Console\Commands;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketCredentialService;
use Illuminate\Console\Command;
use RuntimeException;

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

        $this->info('Polymarket Auth Check');
        $this->line(sprintf('Account: #%d %s', $account->id, $account->name));
        $this->line('Wallet: '.($account->wallet_address ?? '-'));
        $this->line('Env Key: '.($account->env_key_name ?? '-'));
        $this->line('Signature Type: '.(string) $account->signature_type);
        $this->line('Trading: '.($account->is_active ? 'enabled' : 'disabled'));
        $this->line('Credential Status: '.$this->credentialStatusLabel($account->credential_status));
        $this->newLine();

        try {
            $credentialService->ensureSignerPrivateKeyExists($account);
            $result = $credentialService->validateCredentials($account);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $account->refresh();

        $this->line('HTTP Status: '.(string) $result['status']);
        $this->line('Message: '.$result['message']);
        $this->line('Credential Status (latest): '.$this->credentialStatusLabel($account->credential_status));
        $this->line('Last Validation: '.($account->last_validated_at?->toDateTimeString() ?? '-'));
        $this->line('Last Error: '.($account->last_error_code ?? '-'));
        $this->line('API Key: '.$this->maskApiKey($account->api_key));

        if (! $result['ok']) {
            $this->error('Credential check gagal.');

            return self::FAILURE;
        }

        $this->info('Credential check berhasil.');

        return self::SUCCESS;
    }

    private function credentialStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'needs_rotation' => 'Needs Rotation',
            'revoked' => 'Revoked',
            'validation_failed' => 'Validation Failed',
            'pending' => 'Pending',
            default => ucfirst((string) $status),
        };
    }

    private function maskApiKey(?string $apiKey): string
    {
        if ($apiKey === null || trim($apiKey) === '') {
            return '-';
        }

        if (strlen($apiKey) <= 4) {
            return str_repeat('*', strlen($apiKey));
        }

        return substr($apiKey, 0, 3).'****'.substr($apiKey, -4);
    }
}
