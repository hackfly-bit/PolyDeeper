<?php

namespace App\Services\Polymarket;

use App\Models\Market;
use App\Models\MarketToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class PolymarketGammaService
{
    public function fetchMarkets(int $limit = 100, int $offset = 0, bool $active = true, bool $closed = false): array
    {
        $response = Http::baseUrl($this->gammaHost())
            ->timeout((int) config('services.polymarket.timeout_seconds', 15))
            ->acceptJson()
            ->withOptions([
                'verify' => false,
            ])
            ->retry(2, 300)
            ->get('/markets', [
                'active' => $active ? 'true' : 'false',
                'closed' => $closed ? 'true' : 'false',
                'limit' => $limit,
                'offset' => $offset,
            ]);

        $response->throw();

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{inserted:int,updated:int,tokens_upserted:int,pages:int}
     */
    public function syncActiveMarkets(int $pageSize = 100, int $maxPages = 10): array
    {
        $inserted = 0;
        $updated = 0;
        $tokensUpserted = 0;
        $pagesProcessed = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $offset = $page * $pageSize;
            $markets = $this->fetchMarkets($pageSize, $offset, true, false);

            if (count($markets) === 0) {
                break;
            }

            $pagesProcessed++;

            foreach ($markets as $marketPayload) {
                if (! is_array($marketPayload)) {
                    continue;
                }

                $conditionId = (string) ($marketPayload['conditionId'] ?? $marketPayload['condition_id'] ?? '');
                if ($conditionId === '') {
                    continue;
                }

                $attributes = [
                    'slug' => $marketPayload['slug'] ?? null,
                    'question' => $marketPayload['question'] ?? null,
                    'description' => $marketPayload['description'] ?? null,
                    'active' => (bool) ($marketPayload['active'] ?? true),
                    'closed' => (bool) ($marketPayload['closed'] ?? false),
                    'end_date' => $this->parseDate($marketPayload['endDateIso'] ?? $marketPayload['end_date_iso'] ?? null),
                    'minimum_tick_size' => $this->toFloat($marketPayload['minimum_tick_size'] ?? null),
                    'neg_risk' => (bool) ($marketPayload['neg_risk'] ?? false),
                    'raw_payload' => $marketPayload,
                    'last_synced_at' => now(),
                ];

                $existing = Market::query()->where('condition_id', $conditionId)->first();
                $market = Market::query()->updateOrCreate(
                    ['condition_id' => $conditionId],
                    $attributes
                );

                if ($existing === null) {
                    $inserted++;
                } else {
                    $updated++;
                }

                $tokensUpserted += $this->syncMarketTokens($market, $marketPayload);
            }

            if (count($markets) < $pageSize) {
                break;
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'tokens_upserted' => $tokensUpserted,
            'pages' => $pagesProcessed,
        ];
    }

    private function gammaHost(): string
    {
        return rtrim((string) config('services.polymarket.gamma_host', 'https://gamma-api.polymarket.com'), '/');
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function syncMarketTokens(Market $market, array $marketPayload): int
    {
        $tokenPayloads = Arr::wrap($marketPayload['tokens'] ?? []);
        $fallbackTokenIds = Arr::wrap($marketPayload['clobTokenIds'] ?? $marketPayload['clob_token_ids'] ?? []);

        if (count($tokenPayloads) === 0 && count($fallbackTokenIds) > 0) {
            $tokenPayloads = [
                ['token_id' => $fallbackTokenIds[0] ?? null, 'outcome' => 'YES'],
                ['token_id' => $fallbackTokenIds[1] ?? null, 'outcome' => 'NO'],
            ];
        }

        $upserted = 0;

        foreach ($tokenPayloads as $index => $tokenPayload) {
            if (! is_array($tokenPayload)) {
                $tokenPayload = ['token_id' => (string) $tokenPayload];
            }

            $tokenId = (string) ($tokenPayload['token_id'] ?? $tokenPayload['tokenId'] ?? '');
            if ($tokenId === '') {
                continue;
            }

            $outcome = strtoupper((string) ($tokenPayload['outcome'] ?? ($index === 0 ? 'YES' : 'NO')));

            MarketToken::query()->updateOrCreate(
                ['token_id' => $tokenId],
                [
                    'market_id' => $market->id,
                    'outcome' => $outcome,
                    'is_yes' => $outcome === 'YES',
                    'raw_payload' => $tokenPayload,
                ]
            );

            $upserted++;
        }

        return $upserted;
    }
}
