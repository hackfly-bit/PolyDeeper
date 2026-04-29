<?php

namespace App\Http\Controllers;

use App\Models\ExecutionLog;
use App\Models\Market;
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
            ->with([
                'wallet:id,name,address,weight',
                'market:id,condition_id,question,title',
            ])
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

    public function history(Request $request): View
    {
        $selectedType = strtolower(trim((string) $request->input('type', 'all')));
        $selectedStatus = strtolower(trim((string) $request->input('status', 'all')));
        $selectedWalletId = (int) $request->integer('wallet_id', 0);
        $search = trim((string) $request->input('q', ''));
        $fromDate = trim((string) $request->input('from', ''));
        $toDate = trim((string) $request->input('to', ''));

        if (! in_array($selectedType, ['all', 'signal', 'execution'], true)) {
            $selectedType = 'all';
        }

        $wallet = $selectedWalletId > 0 ? Wallet::query()->find($selectedWalletId) : null;

        $signalsQuery = Signal::query()
            ->with('wallet:id,name,address')
            ->latest();

        if ($selectedWalletId > 0) {
            $signalsQuery->where('wallet_id', $selectedWalletId);
        }

        if ($selectedStatus === 'buy') {
            $signalsQuery->where('direction', '>', 0);
        }

        if ($selectedStatus === 'sell') {
            $signalsQuery->where('direction', '<', 0);
        }

        if ($search !== '') {
            $searchKeyword = '%'.strtolower($search).'%';
            $signalsQuery->where(function ($query) use ($searchKeyword): void {
                $query->whereRaw('LOWER(COALESCE(market_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(condition_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(token_id, \'\')) LIKE ?', [$searchKeyword]);
            });
        }

        if ($fromDate !== '') {
            $signalsQuery->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $signalsQuery->whereDate('created_at', '<=', $toDate);
        }

        $executionsQuery = ExecutionLog::query()
            ->latest('occurred_at');

        if ($wallet !== null && $wallet->address !== '') {
            $executionsQuery->where('wallet_address', $wallet->address);
        }

        if ($selectedStatus !== 'all' && ! in_array($selectedStatus, ['buy', 'sell'], true)) {
            $executionsQuery->whereRaw('LOWER(COALESCE(status, \'\')) = ?', [$selectedStatus]);
        }

        if ($search !== '') {
            $searchKeyword = '%'.strtolower($search).'%';
            $executionsQuery->where(function ($query) use ($searchKeyword): void {
                $query->whereRaw('LOWER(COALESCE(market_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(stage, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(action, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(message, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(wallet_address, \'\')) LIKE ?', [$searchKeyword]);
            });
        }

        if ($fromDate !== '') {
            $executionsQuery->whereDate('occurred_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $executionsQuery->whereDate('occurred_at', '<=', $toDate);
        }

        $signals = $signalsQuery
            ->with('market:id,condition_id,question,title')
            ->paginate(20, ['*'], 'signals_page')
            ->withQueryString();
        $executions = $executionsQuery
            ->paginate(20, ['*'], 'executions_page')
            ->withQueryString();

        $marketConditionIds = collect()
            ->merge(
                $signals->getCollection()
                    ->pluck('market_id')
                    ->filter()
                    ->values()
            )
            ->merge(
                $signals->getCollection()
                    ->pluck('condition_id')
                    ->filter()
                    ->values()
            )
            ->merge(
                $executions->getCollection()
                    ->pluck('market_id')
                    ->filter()
                    ->values()
            )
            ->unique()
            ->values();

        $marketTitlesByCondition = Market::query()
            ->whereIn('condition_id', $marketConditionIds)
            ->get(['condition_id', 'question'])
            ->mapWithKeys(function (Market $market): array {
                $title = trim((string) ($market->question ?? ''));

                return [$market->condition_id => $title];
            });

        return view('dashboard.history', [
            'pageTitle' => 'History',
            'selectedType' => $selectedType,
            'selectedStatus' => $selectedStatus,
            'selectedWalletId' => $selectedWalletId,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'walletOptions' => Wallet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'address']),
            'signals' => $signals,
            'executions' => $executions,
            'marketTitlesByCondition' => $marketTitlesByCondition,
        ]);
    }

    public function wallets(): View
    {
        return view('dashboard.wallets', [
            'pageTitle' => 'Tracked Wallets',
            'wallets' => Wallet::query()->latest('last_active')->paginate(20),
        ]);
    }

    public function markers(Request $request): View
    {
        $selectedWalletId = (int) $request->integer('wallet_id', 0);
        $selectedStatus = strtolower(trim((string) $request->input('status', 'all')));
        $searchTitle = trim((string) $request->input('q', ''));

        if (! in_array($selectedStatus, ['all', 'open', 'closed'], true)) {
            $selectedStatus = 'all';
        }

        $markerQuery = WalletTrade::query()
            ->leftJoin('markets', 'markets.condition_id', '=', 'wallet_trades.condition_id')
            ->select([
                'wallet_trades.condition_id',
                DB::raw('MAX(wallet_trades.traded_at) as last_traded_at'),
                DB::raw('MIN(CASE WHEN markets.closed = true OR (markets.end_date IS NOT NULL AND markets.end_date < CURRENT_TIMESTAMP) THEN 1 ELSE 0 END) as status_sort'),
            ])
            ->whereNotNull('wallet_trades.condition_id')
            ->where('wallet_trades.condition_id', '!=', '');

        if ($selectedWalletId > 0) {
            $markerQuery->where('wallet_trades.wallet_id', $selectedWalletId);
        }

        if ($selectedStatus === 'open') {
            $markerQuery->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($statusQuery): void {
                        $statusQuery->where('markets.closed', false)
                            ->orWhereNull('markets.closed');
                    })
                    ->where(function ($dateQuery): void {
                        $dateQuery->whereNull('markets.end_date')
                            ->orWhere('markets.end_date', '>=', now());
                    });
            });
        }

        if ($selectedStatus === 'closed') {
            $markerQuery->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($statusQuery): void {
                        $statusQuery->where('markets.closed', true)
                            ->orWhere('markets.end_date', '<', now());
                    });
            });
        }

        if ($searchTitle !== '') {
            $searchKeyword = '%'.strtolower($searchTitle).'%';

            $markerQuery->whereExists(function ($query) use ($searchKeyword): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($titleQuery) use ($searchKeyword): void {
                        $titleQuery->whereRaw('LOWER(COALESCE(markets.question, \'\')) LIKE ?', [$searchKeyword])
                            ->orWhereRaw('LOWER(COALESCE(markets.title, \'\')) LIKE ?', [$searchKeyword]);
                    });
            });
        }

        $markerRows = $markerQuery
            ->groupBy('wallet_trades.condition_id')
            ->orderBy('status_sort')
            ->orderByDesc('last_traded_at')
            ->paginate(20)
            ->withQueryString();

        $conditionIds = $markerRows->getCollection()
            ->pluck('condition_id')
            ->filter()
            ->values();

        $marketsByCondition = Market::query()
            ->whereIn('condition_id', $conditionIds)
            ->get()
            ->keyBy('condition_id');

        $walletNameExpression = "CASE WHEN wallets.name != '' THEN wallets.name ELSE wallets.address END";
        $walletNamesAggregate = DB::connection()->getDriverName() === 'pgsql'
            ? "STRING_AGG(DISTINCT {$walletNameExpression}, ',')"
            : "GROUP_CONCAT(DISTINCT {$walletNameExpression})";

        $walletAggregateByCondition = WalletTrade::query()
            ->join('wallets', 'wallets.id', '=', 'wallet_trades.wallet_id')
            ->whereIn('wallet_trades.condition_id', $conditionIds)
            ->select([
                'wallet_trades.condition_id',
                DB::raw("{$walletNamesAggregate} AS wallet_names"),
                DB::raw('COUNT(DISTINCT wallet_trades.wallet_id) AS wallet_count'),
            ])
            ->groupBy('wallet_trades.condition_id')
            ->get()
            ->keyBy('condition_id');

        $markerRows->setCollection(
            $markerRows->getCollection()->map(function (WalletTrade $walletTrade) use ($marketsByCondition, $walletAggregateByCondition): array {
                $market = $marketsByCondition->get($walletTrade->condition_id);
                $marketPayload = $market?->raw_payload ?? [];
                $walletAggregate = $walletAggregateByCondition->get($walletTrade->condition_id);
                $walletNames = collect(explode(',', (string) ($walletAggregate->wallet_names ?? '')))
                    ->map(fn (string $name): string => trim($name))
                    ->filter()
                    ->values();
                $eventSlug = (string) ($market->slug ?? '');
                $category = $this->extractMarketCategory($marketPayload);
                $timeRemaining = $market?->end_date !== null
                    ? ($market->end_date->isPast() ? 'Sudah selesai' : $market->end_date->diffForHumans(now(), [
                        'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                        'parts' => 2,
                    ]))
                    : '-';
                $isClosed = ($market?->closed ?? false) || ($market?->end_date?->isPast() ?? false);

                return [
                    'condition_id' => $walletTrade->condition_id,
                    'title' => (string) ($market?->question ?? 'Market tanpa judul'),
                    'category' => $category,
                    'status' => $isClosed ? 'Closed' : 'Open',
                    'wallet_names' => $walletNames->join(', '),
                    'wallet_count' => (int) ($walletAggregate->wallet_count ?? 0),
                    'market_url' => $eventSlug !== '' ? 'https://polymarket.com/event/'.$eventSlug : null,
                    'detail' => [
                        'volume' => $this->extractMarketVolume($marketPayload),
                        'time_remaining' => $timeRemaining,
                        'rules' => $this->extractMarketRules($marketPayload, $market?->description),
                        'context' => $this->extractMarketContext($marketPayload),
                    ],
                ];
            })
        );

        return view('dashboard.markers', [
            'pageTitle' => 'Marker',
            'markers' => $markerRows,
            'walletOptions' => Wallet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'address']),
            'selectedWalletId' => $selectedWalletId,
            'selectedStatus' => $selectedStatus,
            'searchTitle' => $searchTitle,
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

    private function extractMarketCategory(array $marketPayload): string
    {
        $category = data_get($marketPayload, 'category')
            ?? data_get($marketPayload, 'groupItemTitle')
            ?? data_get($marketPayload, 'questionCategory')
            ?? data_get($marketPayload, 'event.title')
            ?? '-';

        return trim((string) $category) !== '' ? (string) $category : '-';
    }

    private function extractMarketVolume(array $marketPayload): string
    {
        $volume = data_get($marketPayload, 'volume')
            ?? data_get($marketPayload, 'volumeNum')
            ?? data_get($marketPayload, 'volume24hr')
            ?? data_get($marketPayload, 'liquidityNum');

        if ($volume === null || $volume === '') {
            return '-';
        }

        if (is_numeric($volume)) {
            return '$'.number_format((float) $volume, 2);
        }

        return (string) $volume;
    }

    private function extractMarketRules(array $marketPayload, ?string $description): string
    {
        $rules = data_get($marketPayload, 'rules')
            ?? data_get($marketPayload, 'marketRules')
            ?? $description
            ?? '-';

        return Str::limit(trim((string) $rules), 320, '...');
    }

    private function extractMarketContext(array $marketPayload): string
    {
        $context = data_get($marketPayload, 'context')
            ?? data_get($marketPayload, 'description')
            ?? data_get($marketPayload, 'event.description')
            ?? data_get($marketPayload, 'event.title')
            ?? '-';

        return Str::limit(trim((string) $context), 320, '...');
    }
}
