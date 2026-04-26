<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountAuditService;
use App\Services\Polymarket\PolymarketAccountOrchestratorService;
use App\Services\Polymarket\PolymarketAuthService;
use App\Services\Polymarket\PolymarketService;
use App\Services\Polymarket\SigningService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OrderExecutionService
{
    public function __construct(
        public PolymarketService $polymarketService,
        public PolymarketAccountOrchestratorService $accountOrchestratorService,
        public PolymarketAccountAuditService $auditService,
        public PolymarketAuthService $authService,
        public SigningService $signingService
    ) {}

    /**
     * @param  array{
     *     condition_id?:string|null,
     *     token_id?:string|null,
     *     order_type?:string|null,
     *     market_ref_id?:int|null,
     *     account_id?:?int
     * }  $context
     * @return array{
     *     success:bool,
     *     order_id?:int,
     *     polymarket_order_id?:?string,
     *     tx_hash?:?string,
     *     executed_at?:\Illuminate\Support\Carbon,
     *     error?:string
     * }
     */
    public function execute(string $marketId, string $side, float $size, float $price, array $context = []): array
    {
        Log::info('Preparing to execute trade on Polymarket CLOB', [
            'market_id' => $marketId,
            'side' => $side,
            'size' => $size,
            'price' => $price,
        ]);
        $account = $this->resolveAccount($context['account_id'] ?? null);
        if ($account === null || ! $account->is_active) {
            return [
                'success' => false,
                'error' => 'Tidak ada account aktif untuk eksekusi order.',
            ];
        }
        if ($account->max_order_size !== null && $size > $account->max_order_size) {
            return [
                'success' => false,
                'error' => 'Order melebihi batas max_order_size account.',
            ];
        }
        if ($account->wallet_address === null || trim($account->wallet_address) === '') {
            return [
                'success' => false,
                'error' => 'Wallet address account belum diisi.',
            ];
        }

        $lockKey = sprintf('trade-throttle:%d', $account->id);
        $cooldown = max(1, $account->cooldown_seconds);
        if (! Cache::add($lockKey, 1, now()->addSeconds($cooldown))) {
            return [
                'success' => false,
                'error' => 'Order ditolak throttle per-account.',
            ];
        }

        $market = $this->resolveMarket($marketId, $context['condition_id'] ?? null, $context['token_id'] ?? null);
        $conditionId = $market?->condition_id ?? ($context['condition_id'] ?? null) ?? $marketId;
        $tokenId = $context['token_id'] ?? null;
        if ($tokenId === null && $market !== null) {
            $tokenId = $market->tokens()
                ->where('is_yes', strtoupper($side) === 'YES')
                ->value('token_id');
        }

        if ($tokenId === null) {
            return [
                'success' => false,
                'error' => 'token_id tidak ditemukan. Pastikan market sinkron dari Gamma API.',
            ];
        }

        $orderPayload = [
            'token_id' => $tokenId,
            'side' => strtoupper($side) === 'YES' ? 'BUY' : 'SELL',
            'price' => $price,
            'size' => $size,
            'order_type' => $context['order_type'] ?? 'GTC',
            'signature_type' => $account->signature_type,
            'owner' => $account->wallet_address,
        ];
        $typedData = $this->buildOrderTypedData($orderPayload, $conditionId, $account);

        try {
            $privateKey = $this->authService->resolveSignerPrivateKey($account->env_key_name);
            $orderPayload['signature'] = $this->signingService->signEip712Payload($typedData, $privateKey);
        } catch (RuntimeException $exception) {
            return [
                'success' => false,
                'error' => 'Gagal sign order: '.$exception->getMessage(),
            ];
        }

        $idempotencyKey = sha1(implode('|', [
            $account->id,
            $conditionId,
            $tokenId,
            strtoupper($side),
            (string) $size,
            (string) $price,
            now()->format('YmdHi'),
        ]));

        $existingOrder = Order::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existingOrder !== null) {
            return [
                'success' => true,
                'order_id' => $existingOrder->id,
                'polymarket_order_id' => $existingOrder->polymarket_order_id,
                'tx_hash' => $existingOrder->tx_hash,
                'executed_at' => $existingOrder->updated_at,
            ];
        }

        $order = Order::query()->create([
            'market_id' => $market?->id ?? ($context['market_ref_id'] ?? null),
            'polymarket_account_id' => $account->id,
            'condition_id' => $conditionId,
            'token_id' => $tokenId,
            'side' => strtoupper($side),
            'order_type' => $orderPayload['order_type'],
            'price' => $price,
            'size' => $size,
            'filled_size' => 0,
            'status' => 'submitting',
            'idempotency_key' => $idempotencyKey,
            'signature_type' => $orderPayload['signature_type'],
            'funder_address' => $account->funder_address,
            'raw_request' => $orderPayload,
        ]);

        try {
            $response = $this->polymarketService->postOrder($orderPayload, $account);
            $response->throw();

            $body = $response->json() ?? [];
            $order->update([
                'status' => (string) ($body['status'] ?? 'submitted'),
                'polymarket_order_id' => $body['orderID'] ?? $body['id'] ?? null,
                'tx_hash' => $body['transactionHash'] ?? null,
                'raw_response' => $body,
            ]);
            $this->accountOrchestratorService->setCooldown($account);

            return [
                'success' => true,
                'order_id' => $order->id,
                'polymarket_order_id' => $order->polymarket_order_id,
                'tx_hash' => $body['transactionHash'] ?? null,
                'executed_at' => now(),
            ];
        } catch (RequestException|ConnectionException $exception) {
            $status = $exception instanceof RequestException ? $exception->response->status() : 0;
            $order->update([
                'status' => 'failed',
                'raw_response' => ['error' => $exception->getMessage()],
            ]);
            if (in_array($status, [401, 403], true)) {
                $this->auditService->log(
                    $account,
                    'runtime.alert',
                    'warning',
                    'Akun terindikasi revoked karena auth gagal berulang.',
                    ['http_status' => $status]
                );
            }

            Log::error('Polymarket CLOB order submit gagal', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function resolveMarket(string $marketId, ?string $conditionId, ?string $tokenId): ?Market
    {
        return Market::query()
            ->where('condition_id', $conditionId ?? $marketId)
            ->when($tokenId !== null, function ($query) use ($tokenId) {
                $query->whereHas('tokens', function ($tokenQuery) use ($tokenId) {
                    $tokenQuery->where('token_id', $tokenId);
                });
            })
            ->first();
    }

    private function resolveAccount(?int $accountId): ?PolymarketAccount
    {
        if ($accountId !== null) {
            return PolymarketAccount::query()->find($accountId);
        }

        return $this->accountOrchestratorService->pickActiveAccount();
    }

    /**
     * @param  array{
     *     token_id:string,
     *     side:string,
     *     price:float,
     *     size:float,
     *     order_type:string,
     *     signature_type:int,
     *     owner:?string
     * }  $orderPayload
     * @return array<string, mixed>
     */
    private function buildOrderTypedData(array $orderPayload, string $conditionId, PolymarketAccount $account): array
    {
        return [
            'domain' => [
                'name' => 'Polymarket CLOB',
                'version' => '1',
                'chainId' => 137,
                'verifyingContract' => '0x0000000000000000000000000000000000000000',
            ],
            'message' => [
                'condition_id' => $conditionId,
                'token_id' => $orderPayload['token_id'],
                'side' => $orderPayload['side'],
                'price' => $orderPayload['price'],
                'size' => $orderPayload['size'],
                'order_type' => $orderPayload['order_type'],
                'signature_type' => $orderPayload['signature_type'],
                'owner' => $account->wallet_address,
                'funder' => $account->funder_address ?? $account->wallet_address,
                'timestamp' => now()->getPreciseTimestamp(3),
            ],
            'types' => [
                'Order' => [
                    ['name' => 'condition_id', 'type' => 'string'],
                    ['name' => 'token_id', 'type' => 'string'],
                    ['name' => 'side', 'type' => 'string'],
                    ['name' => 'price', 'type' => 'double'],
                    ['name' => 'size', 'type' => 'double'],
                    ['name' => 'order_type', 'type' => 'string'],
                    ['name' => 'signature_type', 'type' => 'uint8'],
                    ['name' => 'owner', 'type' => 'address'],
                    ['name' => 'funder', 'type' => 'address'],
                    ['name' => 'timestamp', 'type' => 'uint256'],
                ],
            ],
            'primaryType' => 'Order',
        ];
    }
}
