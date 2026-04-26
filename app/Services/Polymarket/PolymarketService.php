<?php

namespace App\Services\Polymarket;

use App\Models\PolymarketAccount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PolymarketService
{
    public function __construct(
        public PolymarketAuthService $authService,
        public RuntimeHealthService $runtimeHealthService
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function postOrder(array $payload, PolymarketAccount $account): Response
    {
        $path = '/order';
        $headers = $this->authService->buildL2HeadersForAccount($account, 'POST', $path, $payload);

        $response = $this->request()
            ->withHeaders($headers)
            ->post($path, $payload);
        $this->recordRuntimeSignals($response, $account);

        return $response;
    }

    /**
     * @return array{ok:bool,status:int,rows:array<int, array<string, mixed>>}
     */
    public function fetchOpenOrders(int $limit, PolymarketAccount $account): array
    {
        $query = [
            'status' => 'open',
            'limit' => $limit,
        ];
        $requestPath = '/data/orders?'.Arr::query($query);
        $headers = $this->authService->buildL2HeadersForAccount($account, 'GET', $requestPath);

        $response = $this->request()
            ->withHeaders($headers)
            ->get('/data/orders', $query);
        $this->recordRuntimeSignals($response, $account);

        $rows = $response->json();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'rows' => is_array($rows) ? $rows : [],
        ];
    }

    /**
     * @return array{ok:bool,status:int,body:array}
     */
    public function validateCredentials(PolymarketAccount $account): array
    {
        $response = $this->request(2)
            ->withHeaders($this->authService->buildL2HeadersForAccount($account, 'GET', '/data/orders'))
            ->get('/data/orders');
        $this->recordRuntimeSignals($response, $account);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    /**
     * @return array{ok:bool,status:int,body:array}
     */
    public function deriveApiCredentials(PolymarketAccount $account, string $privateKey, int $nonce = 0): array
    {
        $response = $this->request(1)
            ->withHeaders($this->authService->buildL1HeadersForAccount($account, $privateKey, $nonce))
            ->get('/auth/derive-api-key');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    /**
     * @return array{ok:bool,status:int,body:array}
     */
    public function createApiCredentials(PolymarketAccount $account, string $privateKey, int $nonce = 0): array
    {
        $response = $this->request(1)
            ->withHeaders($this->authService->buildL1HeadersForAccount($account, $privateKey, $nonce))
            ->post('/auth/api-key');

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    public function triggerRateLimitMetric(PolymarketAccount $account): void
    {
        $cacheKey = sprintf('polymarket:rate-limit:%d:%d', $account->id, now()->minute);
        Cache::increment($cacheKey);
        Cache::put($cacheKey, Cache::get($cacheKey, 1), now()->addMinutes(5));
        $this->runtimeHealthService->recordRateLimitHit($account, (int) Cache::get($cacheKey, 1));
    }

    private function request(int $maxRetries = 3): PendingRequest
    {
        return Http::baseUrl($this->clobHost())
            ->timeout($this->timeoutSeconds())
            ->acceptJson()
            ->withOptions([
                // 'verify' => $this->tlsVerifyOption(),
                'verify' => false,
            ])
            ->retry(
                $maxRetries,
                function (int $attempt, Throwable $exception): int {
                    $status = $exception instanceof RequestException ? $exception->response?->status() : null;
                    $base = $status === 429 ? 500 : 300;

                    return $base * (2 ** max(0, $attempt - 1));
                },
                function (Throwable $exception): bool {
                    $status = $exception instanceof RequestException ? $exception->response?->status() : null;

                    return in_array($status, [429, 500, 502, 503, 504], true);
                }
            );
    }

    private function recordRuntimeSignals(Response $response, ?PolymarketAccount $account = null): void
    {
        if (! $account instanceof PolymarketAccount) {
            return;
        }

        $status = $response->status();
        if ($status === 429) {
            $this->triggerRateLimitMetric($account);
        }
        if (in_array($status, [401, 403], true)) {
            $this->runtimeHealthService->recordAuthFailure($account, $status, 'Auth gagal saat hit endpoint CLOB.');
        }

        $body = (array) ($response->json() ?? []);
        $message = strtolower((string) ($body['error'] ?? $body['message'] ?? ''));
        if (str_contains($message, 'timestamp')) {
            $this->runtimeHealthService->recordTimestampMismatch($account, $message);
        }
    }

    private function clobHost(): string
    {
        return rtrim((string) config('services.polymarket.clob_host', 'https://clob.polymarket.com'), '/');
    }

    private function timeoutSeconds(): int
    {
        return (int) config('services.polymarket.timeout_seconds', 15);
    }

    /**
     * @return bool|string
     */
    private function tlsVerifyOption(): bool|string
    {
        $caBundle = trim((string) config('services.polymarket.ca_bundle', ''));

        if ($caBundle !== '') {
            return $caBundle;
        }

        return (bool) config('services.polymarket.tls_verify', true);
    }
}
