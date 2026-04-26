<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

class SettingsHealthCheck extends Component
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $checks = [];

    public ?string $lastRunAt = null;

    public function mount(): void
    {
        $this->checks = [
            'redis' => $this->pendingCheck('Redis'),
            'queue' => $this->pendingCheck('Queue'),
            'database' => $this->pendingCheck('Database'),
            'cache' => $this->pendingCheck('Cache'),
            'polymarket' => $this->pendingCheck('Endpoint Polymarket'),
        ];
    }

    public function runAllChecks(): void
    {
        $this->runCheck('redis');
        $this->runCheck('queue');
        $this->runCheck('database');
        $this->runCheck('cache');
        $this->runCheck('polymarket');
        $this->lastRunAt = now()->format('Y-m-d H:i:s');
    }

    public function runCheck(string $checkKey): void
    {
        $this->checks[$checkKey] = match ($checkKey) {
            'redis' => $this->testRedis(),
            'queue' => $this->testQueue(),
            'database' => $this->testDatabase(),
            'cache' => $this->testCache(),
            'polymarket' => $this->testPolymarketEndpoints(),
            default => $this->pendingCheck(Str::headline($checkKey)),
        };

        $this->lastRunAt = now()->format('Y-m-d H:i:s');
    }

    public function render(): View
    {
        return view('dashboard.components.settings-health-check', [
            'overallStatus' => $this->overallStatus(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingCheck(string $label): array
    {
        return [
            'label' => $label,
            'state' => 'pending',
            'message' => 'Belum diuji.',
            'checked_at' => null,
            'duration_ms' => null,
            'meta' => [],
        ];
    }

    /**
     * @param  callable(): array{state: string, message: string, meta?: array<string, mixed>}  $callback
     * @return array<string, mixed>
     */
    private function measureCheck(string $label, callable $callback): array
    {
        $startedAt = microtime(true);

        try {
            $result = $callback();
        } catch (Throwable $exception) {
            $result = [
                'state' => 'down',
                'message' => Str::limit($exception->getMessage(), 180),
                'meta' => [],
            ];
        }

        return [
            'label' => $label,
            'state' => $result['state'],
            'message' => $result['message'],
            'checked_at' => now()->format('H:i:s'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'meta' => $result['meta'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function testRedis(): array
    {
        return $this->measureCheck('Redis', function (): array {
            $response = Redis::ping();
            $reachable = $this->isRedisPingSuccessful($response);
            $readableResponse = $this->normalizeRedisPingResponse($response);

            return [
                'state' => $reachable ? 'healthy' : 'down',
                'message' => $reachable
                    ? 'Redis merespons ping dengan normal.'
                    : 'Redis tidak merespons seperti yang diharapkan.',
                'meta' => [
                    'client' => (string) Config::get('database.redis.client', 'n/a'),
                    'response' => $readableResponse,
                ],
            ];
        });
    }

    private function isRedisPingSuccessful(mixed $response): bool
    {
        if ($response === true) {
            return true;
        }

        $normalized = $this->normalizeRedisPingResponse($response);

        return strtoupper(ltrim($normalized, '+')) === 'PONG';
    }

    private function normalizeRedisPingResponse(mixed $response): string
    {
        if (is_object($response) && method_exists($response, 'getPayload')) {
            /** @var mixed $payload */
            $payload = $response->getPayload();
            $response = $payload;
        }

        if (is_scalar($response)) {
            return (string) $response;
        }

        return get_debug_type($response);
    }

    /**
     * @return array<string, mixed>
     */
    private function testQueue(): array
    {
        return $this->measureCheck('Queue', function (): array {
            $queueConnection = (string) Config::get('queue.default', '');

            if ($queueConnection === '') {
                return [
                    'state' => 'missing',
                    'message' => 'Queue connection belum dikonfigurasi.',
                    'meta' => [],
                ];
            }

            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            return [
                'state' => 'healthy',
                'message' => 'Queue storage bisa diakses dan siap digunakan.',
                'meta' => [
                    'connection' => $queueConnection,
                    'pending_jobs' => $pendingJobs,
                    'failed_jobs' => $failedJobs,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function testDatabase(): array
    {
        return $this->measureCheck('Database', function (): array {
            $result = DB::select('SELECT 1 as ready');
            $ready = (int) ($result[0]->ready ?? 0) === 1;

            return [
                'state' => $ready ? 'healthy' : 'down',
                'message' => $ready
                    ? 'Koneksi database aktif dan query dasar berhasil.'
                    : 'Database merespons tetapi hasil query tidak valid.',
                'meta' => [
                    'connection' => (string) Config::get('database.default', 'n/a'),
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function testCache(): array
    {
        return $this->measureCheck('Cache', function (): array {
            $cacheStore = (string) Config::get('cache.default', '');

            if ($cacheStore === '') {
                return [
                    'state' => 'missing',
                    'message' => 'Cache store belum dikonfigurasi.',
                    'meta' => [],
                ];
            }

            $cacheKey = 'health-check:'.Str::random(12);
            Cache::put($cacheKey, 'ready', now()->addMinute());
            $storedValue = Cache::get($cacheKey);
            Cache::forget($cacheKey);

            return [
                'state' => $storedValue === 'ready' ? 'healthy' : 'down',
                'message' => $storedValue === 'ready'
                    ? 'Cache read/write berjalan normal.'
                    : 'Cache gagal membaca kembali nilai yang ditulis.',
                'meta' => [
                    'store' => $cacheStore,
                ],
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function testPolymarketEndpoints(): array
    {
        return $this->measureCheck('Endpoint Polymarket', function (): array {
            $probes = [
                $this->probeEndpoint(
                    'CLOB API',
                    (string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'),
                    '/time'
                ),
                $this->probeEndpoint(
                    'Gamma API',
                    (string) config('services.polymarket.gamma_host', 'https://gamma-api.polymarket.com'),
                    '/markets',
                    [
                        'limit' => 1,
                        'active' => 'true',
                        'closed' => 'false',
                    ]
                ),
                $this->probeEndpoint(
                    'Data API',
                    (string) config('services.polymarket.data_host', 'https://data-api.polymarket.com'),
                    '/'
                ),
            ];

            $states = collect($probes)->pluck('state');
            $state = $states->contains('down')
                ? 'down'
                : ($states->contains('degraded') || $states->contains('missing') ? 'degraded' : 'healthy');

            $healthyCount = collect($probes)->where('state', 'healthy')->count();

            return [
                'state' => $state,
                'message' => sprintf(
                    '%d dari %d endpoint Polymarket merespons sehat.',
                    $healthyCount,
                    count($probes),
                ),
                'meta' => [
                    'endpoints' => $probes,
                ],
            ];
        });
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<string, mixed>
     */
    private function probeEndpoint(string $name, string $host, string $path, array $query = []): array
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
                'http_status' => null,
                'message' => 'Host endpoint belum diisi.',
            ];
        }

        try {
            $response = Http::baseUrl($normalizedHost)
                ->acceptJson()
                ->timeout((int) config('services.polymarket.timeout_seconds', 15))
                ->withOptions([
                    'verify' => false,
                ])
                ->get($path, $query);

            $status = $response->status();

            return [
                'name' => $name,
                'host' => $normalizedHost,
                'probe' => $probe,
                'state' => match (true) {
                    $response->successful() => 'healthy',
                    $status >= 500 => 'down',
                    default => 'degraded',
                },
                'http_status' => $status,
                'message' => $response->successful()
                    ? 'Endpoint merespons normal.'
                    : 'Endpoint merespons HTTP '.$status.'.',
            ];
        } catch (Throwable $exception) {
            return [
                'name' => $name,
                'host' => $normalizedHost,
                'probe' => $probe,
                'state' => 'down',
                'http_status' => null,
                'message' => Str::limit($exception->getMessage(), 160),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function overallStatus(): array
    {
        $states = collect($this->checks)->pluck('state')->filter();

        if ($states->isEmpty() || $states->every(fn (string $state): bool => $state === 'pending')) {
            return [
                'state' => 'pending',
                'label' => 'Belum Dites',
            ];
        }

        if ($states->contains('down')) {
            return [
                'state' => 'down',
                'label' => 'Belum Siap',
            ];
        }

        if ($states->contains('degraded') || $states->contains('missing')) {
            return [
                'state' => 'degraded',
                'label' => 'Perlu Cek',
            ];
        }

        return [
            'state' => 'healthy',
            'label' => 'Siap Dipakai',
        ];
    }
}
