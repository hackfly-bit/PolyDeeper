<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWalletTradeJob;
use App\Models\ExecutionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhooks from Moralis / Alchemy
     */
    public function handle(Request $request): JsonResponse
    {
        // Example for Moralis streams
        $payload = $request->all();

        Log::info('Webhook received', ['payload' => $payload]);

        // Basic validation
        if (! $request->has('logs')) {
            ExecutionLog::create([
                'stage' => 'webhook_ignored',
                'status' => 'warning',
                'message' => 'Webhook ignored because logs key is missing.',
                'context' => ['payload_keys' => array_keys($payload)],
                'occurred_at' => now(),
            ]);

            return response()->json(['status' => 'ignored']);
        }

        // Parse payload from webhook event and apply dedupe key per tx/log index.
        foreach ($request->input('logs') as $log) {
            $txHash = (string) ($log['transactionHash'] ?? '');
            $logIndex = (string) ($log['logIndex'] ?? '');
            $dedupeKey = 'webhook_trade:'.sha1($txHash.'|'.$logIndex);

            if (! Redis::setnx($dedupeKey, 1)) {
                continue;
            }
            Redis::expire($dedupeKey, 3600);

            $tradeData = [
                'wallet' => $log['address'] ?? '0xUNKNOWN',
                'market_id' => (string) ($log['conditionId'] ?? $log['condition_id'] ?? 'UNKNOWN'),
                'condition_id' => (string) ($log['conditionId'] ?? $log['condition_id'] ?? ''),
                'token_id' => $log['tokenId'] ?? $log['token_id'] ?? null,
                'side' => strtoupper((string) ($log['side'] ?? 'YES')),
                'price' => (float) ($log['price'] ?? 0),
                'size' => (float) ($log['size'] ?? 0),
                'tx_hash' => $txHash !== '' ? $txHash : null,
                'timestamp' => (int) ($log['timestamp'] ?? time()),
            ];

            ExecutionLog::create([
                'stage' => 'webhook_received',
                'market_id' => $tradeData['market_id'],
                'wallet_address' => $tradeData['wallet'],
                'action' => 'INGEST',
                'status' => 'info',
                'message' => 'Webhook event accepted and queued for trade processing.',
                'context' => [
                    'side' => $tradeData['side'],
                    'price' => $tradeData['price'],
                    'size' => $tradeData['size'],
                ],
                'occurred_at' => now(),
            ]);

            ProcessWalletTradeJob::dispatch($tradeData);
        }

        return response()->json(['status' => 'success']);
    }
}
