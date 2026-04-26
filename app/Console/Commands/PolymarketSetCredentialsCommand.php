<?php

namespace App\Console\Commands;

use App\Services\Polymarket\PolymarketConfigService;
use Illuminate\Console\Command;

class PolymarketSetCredentialsCommand extends Command
{
    protected $signature = 'polymarket:set-credentials
        {--address= : Signer address untuk header POLY_ADDRESS}
        {--funder= : Funder / proxy address}
        {--api-key= : API key L2}
        {--api-secret= : API secret L2 (base64)}
        {--api-passphrase= : API passphrase L2}
        {--signature-type= : Signature type (0 EOA, 1 POLY_PROXY, 2 GNOSIS_SAFE)}';

    protected $description = 'Simpan kredensial Polymarket dinamis ke database terenkripsi.';

    public function handle(PolymarketConfigService $configService): int
    {
        $configService->storeTradingConfig([
            'address' => $this->option('address'),
            'funder' => $this->option('funder'),
            'api_key' => $this->option('api-key'),
            'api_secret' => $this->option('api-secret'),
            'api_passphrase' => $this->option('api-passphrase'),
            'signature_type' => $this->option('signature-type'),
        ]);

        $this->info('Kredensial Polymarket berhasil disimpan ke database.');

        return self::SUCCESS;
    }
}
