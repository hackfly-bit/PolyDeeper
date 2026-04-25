<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\ProcessWalletTradeJob;
use Illuminate\Http\JsonResponse;
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

        Log::info("Webhook received", ['payload' => $payload]);

        // Basic validation
        if (!$request->has('logs')) {
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

            ProcessWalletTradeJob::dispatch($tradeData);
        }

        return response()->json(['status' => 'success']);
    }
}