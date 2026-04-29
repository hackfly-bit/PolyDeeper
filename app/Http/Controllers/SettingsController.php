<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HasDashboardRuntimeData;
use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountOrchestratorService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SettingsController extends Controller
{
    use HasDashboardRuntimeData;

    public function index(
        PolymarketAccountOrchestratorService $accountOrchestratorService
    ): View {
        $selectedAccount = $accountOrchestratorService->pickActiveAccount();

        return view('dashboard.settings', [
            'pageTitle' => 'System Settings',
            'runtime' => $this->runtimeStatus(),
            'polymarketAccounts' => PolymarketAccount::query()
                ->orderBy('priority')
                ->orderBy('name')
                ->get(['id', 'name', 'wallet_address', 'credential_status', 'is_active']),
            'selectedPolymarketAccount' => $selectedAccount,
            'selectedPolymarketMaskedApiKey' => $this->maskApiKey($selectedAccount?->api_key),
            'selectedPolymarketWalletAccounts' => $this->walletAccountsForAddress($selectedAccount?->wallet_address),
            'polymarketServerStatuses' => $this->polymarketServerStatuses(),
        ]);
    }

    public function selectPolymarketAccount(
        Request $request,
        PolymarketAccountOrchestratorService $accountOrchestratorService
    ): RedirectResponse {
        $validated = $request->validate([
            'polymarket_account_id' => ['required', 'integer', 'exists:polymarket_accounts,id'],
        ]);

        $account = PolymarketAccount::query()->findOrFail($validated['polymarket_account_id']);
        $accountOrchestratorService->selectActiveAccount($account);

        return redirect()
            ->route('settings')
            ->with('settings_success', 'Account Polymarket aktif berhasil dipilih.');
    }

    private function maskApiKey(?string $apiKey): ?string
    {
        if ($apiKey === null || trim((string) $apiKey) === '') {
            return null;
        }

        $keyLength = strlen($apiKey);

        if ($keyLength <= 4) {
            return str_repeat('*', $keyLength);
        }

        return substr($apiKey, 0, 3).'****'.substr($apiKey, -4);
    }

    /**
     * @return Collection<int, PolymarketAccount>
     */
    private function walletAccountsForAddress(?string $walletAddress)
    {
        $normalizedWalletAddress = trim((string) $walletAddress);

        if ($normalizedWalletAddress === '') {
            return (new PolymarketAccount())->newCollection();
        }

        return PolymarketAccount::query()
            ->whereRaw('LOWER(wallet_address) = ?', [strtolower($normalizedWalletAddress)])
            ->orderByDesc('is_active')
            ->orderByDesc('last_validated_at')
            ->orderBy('priority')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'wallet_address',
                'credential_status',
                'env_key_name',
                'is_active',
                'last_validated_at',
                'last_error_code',
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function polymarketServerStatuses(): array
    {
        return [
            $this->probePolymarketServer(
                'CLOB API',
                (string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'),
                '/time'
            ),
            $this->probePolymarketServer(
                'Gamma API',
                (string) config('services.polymarket.gamma_host', 'https://gamma-api.polymarket.com'),
                '/markets',
                [
                    'limit' => 1,
                    'active' => 'true',
                    'closed' => 'false',
                ]
            ),
            $this->probePolymarketServer(
                'Data API',
                (string) config('services.polymarket.data_host', 'https://data-api.polymarket.com'),
                '/'
            ),
        ];
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function probePolymarketServer(string $name, string $host, string $path, array $query = []): array
    {
        $normalizedHost = rtrim(trim($host), '/');
        $probe = $path;

        if ($query !== []) {
            $probe .= '?'.http_build_query($query);
        }

        if ($normalizedHost === '') {
            return [
                'name' => $name,
                'host' => '-',
                'probe' => $probe,
                'state' => 'missing',
                'label' => 'Belum dikonfigurasi',
                'http_status' => null,
                'message' => 'Host endpoint belum diisi pada konfigurasi.',
            ];
        }

        try {
            $response = Http::baseUrl($normalizedHost)
                ->timeout((int) config('services.polymarket.timeout_seconds', 15))
                ->acceptJson()
                ->withOptions([
                    // 'verify' => $this->polymarketTlsVerifyOption(),
                    'verify' => false,
                ])
                ->get($path, $query);

            $status = $response->status();

            [$state, $label] = match (true) {
                $response->successful() => ['healthy', 'Online'],
                $status >= 500 => ['down', 'Server error'],
                default => ['degraded', 'Respons tidak ideal'],
            };

            return [
                'name' => $name,
                'host' => $normalizedHost,
                'probe' => $probe,
                'state' => $state,
                'label' => $label,
                'http_status' => $status,
                'message' => $response->successful()
                    ? 'Endpoint merespons normal.'
                    : 'Endpoint merespons HTTP '.$status.'.',
            ];
        } catch (ConnectionException $exception) {
            return [
                'name' => $name,
                'host' => $normalizedHost,
                'probe' => $probe,
                'state' => 'down',
                'label' => 'Tidak terhubung',
                'http_status' => null,
                'message' => Str::limit($exception->getMessage(), 160),
            ];
        } catch (Throwable $exception) {
            return [
                'name' => $name,
                'host' => $normalizedHost,
                'probe' => $probe,
                'state' => 'degraded',
                'label' => 'Gagal diproses',
                'http_status' => null,
                'message' => Str::limit($exception->getMessage(), 160),
            ];
        }
    }
}
