<?php

namespace App\Console\Commands;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountAuditService;
use Illuminate\Console\Command;

class PolymarketCredentialRotateCommand extends Command
{
    protected $signature = 'polymarket:rotate-credentials
        {--days=30 : Umur credential (hari) sebelum ditandai needs_rotation}';

    protected $description = 'Tandai account aktif menjadi needs_rotation berdasarkan umur credential';

    /**
     * Execute the console command.
     */
    public function handle(PolymarketAccountAuditService $auditService): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $accounts = PolymarketAccount::query()
            ->where('is_active', true)
            ->whereIn('credential_status', ['active', 'needs_rotation'])
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_rotated_at')
                    ->orWhere('last_rotated_at', '<=', $threshold);
            })
            ->get();

        foreach ($accounts as $account) {
            $account->update(['credential_status' => 'needs_rotation']);
            $auditService->log(
                $account,
                'credential.rotate.scheduled',
                'info',
                'Scheduler menandai credential account needs_rotation.',
                ['threshold_days' => $days]
            );
        }

        $this->info('Account yang ditandai needs_rotation: '.$accounts->count());

        return self::SUCCESS;
    }
}
