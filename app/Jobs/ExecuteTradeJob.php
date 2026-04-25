<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\TradeExecutorService;
use App\Models\Position;

class ExecuteTradeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $marketId;
    protected string $side;
    protected float $size;
    protected float $price;

    /**
     * Create a new job instance.
     */
    public function __construct(string $marketId, string $side, float $size, float $price)
    {
        $this->marketId = $marketId;
        $this->side = $side;
        $this->size = $size;
        $this->price = $price;
    }

    /**
     * Execute the job.
     */
    public function handle(TradeExecutorService $executor): void
    {
        $result = $executor->execute($this->marketId, $this->side, $this->size, $this->price);

        if ($result['success']) {
            Position::create([
                'market_id' => $this->marketId,
                'side' => $this->side,
                'entry_price' => $this->price,
                'size' => $this->size,
                'status' => 'open',
            ]);
        }
    }
}