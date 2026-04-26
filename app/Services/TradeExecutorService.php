<?php

namespace App\Services;

class TradeExecutorService
{
    public function __construct(
        public OrderExecutionService $orderExecutionService
    ) {}

    /**
     * @param  array{
     *     condition_id?:string|null,
     *     token_id?:string|null,
     *     order_type?:string|null,
     *     market_ref_id?:int|null
     * }  $context
     */
    public function execute(string $marketId, string $side, float $size, float $price, array $context = []): array
    {
        return $this->orderExecutionService->execute($marketId, $side, $size, $price, $context);
    }
}
