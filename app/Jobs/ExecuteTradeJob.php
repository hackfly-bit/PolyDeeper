<?php

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Models\Position;
use App\Services\TradeExecutorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

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
        ExecutionLog::create([
            'stage' => 'trade_execution_started',
            'market_id' => $this->marketId,
            'action' => 'BUY '.$this->side,
            'status' => 'info',
            'message' => 'Trade executor started.',
            'context' => [
                'size' => $this->size,
                'price' => $this->price,
            ],
            'occurred_at' => now(),
        ]);

        try {
            $result = $executor->execute($this->marketId, $this->side, $this->size, $this->price);

            if ($result['success']) {
                Position::create([
                    'market_id' => $this->marketId,
                    'side' => $this->side,
                    'entry_price' => $this->price,
                    'size' => $this->size,
                    'status' => 'open',
                ]);

                ExecutionLog::create([
                    'stage' => 'trade_executed',
                    'market_id' => $this->marketId,
                    'action' => 'BUY '.$this->side,
                    'status' => 'success',
                    'message' => 'Trade executed and position opened.',
                    'context' => [
                        'tx_hash' => $result['tx_hash'] ?? null,
                        'size' => $this->size,
                        'price' => $this->price,
                    ],
                    'occurred_at' => now(),
                ]);

                return;
            }

            ExecutionLog::create([
                'stage' => 'trade_execution_failed',
                'market_id' => $this->marketId,
                'action' => 'BUY '.$this->side,
                'status' => 'error',
                'message' => 'Trade execution returned unsuccessful result.',
                'context' => [
                    'result' => $result,
                ],
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            ExecutionLog::create([
                'stage' => 'trade_execution_failed',
                'market_id' => $this->marketId,
                'action' => 'BUY '.$this->side,
                'status' => 'error',
                'message' => 'Trade execution threw an exception.',
                'context' => [
                    'error' => $exception->getMessage(),
                ],
                'occurred_at' => now(),
            ]);

            throw $exception;
        }
    }
}
