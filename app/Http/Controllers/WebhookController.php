<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWalletTradeJob;
use App\Models\ExecutionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

        // Parse payload (Mock parsing logic)
        foreach ($request->input('logs') as $log) {
            // Extract trade data from smart contract event
            $tradeData = [
                'wallet' => $log['address'] ?? '0xUNKNOWN',
                'market_id' => 'TRUMP_2028', // parsed from topic/data
                'side' => 'YES',
                'price' => 0.65,
                'size' => 500,
                'timestamp' => time(),
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
