<?php

namespace App\Http\Controllers;

use App\Models\ExecutionLog;
use App\Models\PolymarketAccount;
use App\Models\Position;
use App\Models\Signal;
use App\Models\Wallet;
use App\Models\WalletTrade;
use App\Services\Polymarket\PolymarketAccountOrchestratorService;
use App\Services\Polymarket\PolymarketWalletStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class DashboardController extends Controller
{
    public function index(): View
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $oneHourAgo = $now->copy()->subHour();

        $trackedWallets = Wallet::count();
        $tradesToday = WalletTrade::query()
            ->whereDate('traded_at', $today)
            ->count();
        $signalsOneHour = Signal::query()
            ->where('created_at', '>=', $oneHourAgo)
            ->count();
        $openPositionsQuery = Position::query()->where('status', 'open');
        $openPositionsCount = (clone $openPositionsQuery)->count();
        $activeExposure = (clone $openPositionsQuery)
            ->selectRaw('COALESCE(SUM(size * entry_price), 0) as total_exposure')
            ->value('total_exposure');
        $queueBacklog = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $logsToday = ExecutionLog::query()
            ->whereDate('occurred_at', $today);

        $recentSignals = Signal::query()
            ->with('wallet:id,address,weight')
            ->latest()
            ->limit(8)
            ->get();

        $recentExecutions = ExecutionLog::query()
            ->whereIn('stage', [
                'fusion_decision',
                'risk_rejected',
                'risk_passed',
                'trade_execution_started',
                'trade_executed',
                'trade_execution_failed',
            ])
            ->latest()
            ->limit(8)
            ->get();

        $walletPerformance = Wallet::query()
            ->latest('last_active')
            ->limit(10)
            ->get();

        $pipeline = [
            'webhook' => (clone $logsToday)->where('stage', 'webhook_received')->count(),
            'trade' => (clone $logsToday)->where('stage', 'trade_saved')->count(),
            'signal' => (clone $logsToday)->where('stage', 'signal_normalized')->count(),
            'fusion' => (clone $logsToday)->where('stage', 'fusion_decision')->count(),
            'risk' => (clone $logsToday)->where('stage', 'risk_passed')->count(),
            'execution' => (clone $logsToday)->where('stage', 'trade_executed')->count(),
        ];

        $riskAlerts = ExecutionLog::query()
            ->where('stage', 'risk_rejected')
            ->latest()
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'pageTitle' => 'Dashboard',
            'stats' => [
                'tracked_wallets' => $trackedWallets,
                'trades_today' => $tradesToday,
                'signals_1h' => $signalsOneHour,
                'open_positions' => $openPositionsCount,
                'active_exposure' => (float) $activeExposure,
                'queue_backlog' => $queueBacklog,
                'failed_jobs' => $failedJobs,
            ],
            'pipeline' => $pipeline,
            'recentSignals' => $recentSignals,
            'recentExecutions' => $recentExecutions,
            'walletPerformance' => $walletPerformance,
            'runtime' => $this->runtimeStatus(),
            'errorHighlights' => $this->latestErrorHighlights(),
            'riskAlerts' => $riskAlerts,
        ]);
    }

    public function positions(): View
    {
        return view('dashboard.positions', [
            'pageTitle' => 'Positions',
            'positions' => Position::query()->latest()->paginate(15),
        ]);
    }

    public function signals(): View
    {
        return view('dashboard.signals', [
            'pageTitle' => 'Signals',
            'signals' => Signal::query()
                ->with('wallet:id,address,weight')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function wallets(): View
    {
        return view('dashboard.wallets', [
            'pageTitle' => 'Tracked Wallets',
            'wallets' => Wallet::query()->latest('last_active')->paginate(20),
        ]);
    }

    public function storeWallet(
        Request $request,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse
    {
        if ($request->filled('address')) {
            $request->merge([
                'address' => $polymarketWalletStatsService->normalizeAddress((string) $request->input('address')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255', 'unique:wallets,address'],
        ]);

        try {
            Wallet::query()->create(
                $polymarketWalletStatsService->payloadForWallet(
                    $validated['name'],
                    $validated['address'],
                )
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil ditambahkan.');
    }

    public function updateWallet(
        Request $request,
        Wallet $wallet,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse
    {
        if ($request->filled('address')) {
            $request->merge([
                'address' => $polymarketWalletStatsService->normalizeAddress((string) $request->input('address')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('wallets', 'address')->ignore($wallet->id),
            ],
        ]);

        try {
            $wallet->update(
                $polymarketWalletStatsService->payloadForWallet(
                    $validated['name'],
                    $validated['address'],
                )
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil diperbarui.');
    }

    public function refreshWallet(
        Wallet $wallet,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse
    {
        try {
            $polymarketWalletStatsService->syncWallet($wallet);
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()]);
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil diperbarui dari Polymarket.');
    }

    public function destroyWallet(Wallet $wallet): RedirectResponse
    {
        $wallet->delete();

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil dihapus.');
    }

    public function settings(
        PolymarketAccountOrchestratorService $accountOrchestratorService
    ): View
    {
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
        if ($apiKey === null || trim($apiKey) === '') {
            return null;
        }

        $keyLength = strlen($apiKey);

        if ($keyLength <= 4) {
            return str_repeat('*', $keyLength);
        }

        return substr($apiKey, 0, 3).'****'.substr($apiKey, -4);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PolymarketAccount>
     */
    private function walletAccountsForAddress(?string $walletAddress)
    {
        $normalizedWalletAddress = trim((string) $walletAddress);

        if ($normalizedWalletAddress === '') {
            return PolymarketAccount::newCollection();
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

    /**
     * @return bool|string
     */
    private function polymarketTlsVerifyOption(): bool|string
    {
        $caBundle = trim((string) config('services.polymarket.ca_bundle', ''));

        if ($caBundle !== '') {
            return $caBundle;
        }

        return (bool) config('services.polymarket.tls_verify', true);
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeStatus(): array
    {
        $redisReachable = false;
        $redisError = null;

        try {
            $redisReachable = $this->isRedisPingSuccessful(Redis::ping());
        } catch (Throwable $exception) {
            $redisError = Str::limit($exception->getMessage(), 160);
        }

        return [
            'app_env' => Config::get('app.env'),
            'queue_connection' => Config::get('queue.default'),
            'cache_store' => Config::get('cache.default'),
            'redis_client' => Config::get('database.redis.client'),
            'redis_reachable' => $redisReachable,
            'redis_error' => $redisError,
            'jobs_pending' => DB::table('jobs')->count(),
            'jobs_failed' => DB::table('failed_jobs')->count(),
        ];
    }

    private function isRedisPingSuccessful(mixed $response): bool
    {
        if ($response === true) {
            return true;
        }

        if (is_object($response) && method_exists($response, 'getPayload')) {
            /** @var mixed $payload */
            $payload = $response->getPayload();
            $response = $payload;
        }

        if (! is_scalar($response)) {
            return false;
        }

        return strtoupper(ltrim((string) $response, '+')) === 'PONG';
    }

    /**
     * @return array<int, string>
     */
    private function latestErrorHighlights(int $limit = 6): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return [];
        }

        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        $highlights = [];

        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim($lines[$index]);

            if ($line === '') {
                continue;
            }

            if (! Str::contains($line, ['.ERROR:', 'exception', 'Connection refused'])) {
                continue;
            }

            $highlights[] = Str::limit($line, 220);

            if (count($highlights) >= $limit) {
                break;
            }
        }

        return $highlights;
    }
}
