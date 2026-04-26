<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketService;

class OrderSyncService
{
    public function __construct(
        public PolymarketService $polymarketService
    ) {}

    /**
     * @return array{synced:int}
     */
    public function syncOpenOrders(int $limit = 200, ?PolymarketAccount $account = null): array
    {
        $response = $this->polymarketService->fetchOpenOrders($limit, $account);
        if (! $response['ok']) {
            return ['synced' => 0];
        }

        $rows = $response['rows'];
        $synced = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $polymarketOrderId = (string) ($row['orderID'] ?? $row['id'] ?? '');
            if ($polymarketOrderId === '') {
                continue;
            }

            $order = Order::query()
                ->where('polymarket_order_id', $polymarketOrderId)
                ->when($account !== null, function ($query) use ($account) {
                    $query->where('polymarket_account_id', $account->id);
                })
                ->first();

            if ($order === null) {
                continue;
            }

            $order->update([
                'status' => $row['status'] ?? $order->status,
                'filled_size' => (float) ($row['filledSize'] ?? $row['filled_size'] ?? $order->filled_size),
                'raw_response' => $row,
            ]);

            $synced++;
        }

        return ['synced' => $synced];
    }
}
