<?php

namespace App\Services\Polymarket;

use App\Models\Market;
use App\Models\MarketToken;
use App\Models\WalletTrade;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class PolymarketGammaService
{
    /**
     * @param  Collection<int, string>|array<int, string>  $conditionIds
     * @return array{requested:int,found:int,inserted:int,updated:int,tokens_upserted:int,missing:int}
     */
    public function syncMarketsByConditionIds(Collection|array $conditionIds): array
    {
        $ids = collect($conditionIds)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values();

        $requested = $ids->count();
        $found = 0;
        $inserted = 0;
        $updated = 0;
        $tokensUpserted = 0;

        foreach ($ids as $conditionId) {
            $marketPayload = $this->fetchMarketByConditionId($conditionId);

            if (! is_array($marketPayload)) {
                continue;
            }

            $found++;
            $persistResult = $this->persistMarketPayload($marketPayload);
            $inserted += $persistResult['inserted'];
            $updated += $persistResult['updated'];
            $tokensUpserted += $persistResult['tokens_upserted'];
        }

        return [
            'requested' => $requested,
            'found' => $found,
            'inserted' => $inserted,
            'updated' => $updated,
            'tokens_upserted' => $tokensUpserted,
            'missing' => max(0, $requested - $found),
        ];
    }

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
    public function syncActiveMarkets(int $pageSize = 100, int $maxPages = 10, bool $watchedOnly = true): array
    {
        $inserted = 0;
        $updated = 0;
        $tokensUpserted = 0;
        $pagesProcessed = 0;

        if ($watchedOnly) {
            $watchedConditionIds = $this->watchedConditionIds($pageSize * $maxPages);

            foreach ($watchedConditionIds as $conditionId) {
                $marketPayload = $this->fetchActiveMarketByConditionId($conditionId);
                $pagesProcessed++;

                if (! is_array($marketPayload)) {
                    continue;
                }

                $persistResult = $this->persistMarketPayload($marketPayload);
                $inserted += $persistResult['inserted'];
                $updated += $persistResult['updated'];
                $tokensUpserted += $persistResult['tokens_upserted'];
            }

            return [
                'inserted' => $inserted,
                'updated' => $updated,
                'tokens_upserted' => $tokensUpserted,
                'pages' => $pagesProcessed,
            ];
        }

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

                $persistResult = $this->persistMarketPayload($marketPayload);
                $inserted += $persistResult['inserted'];
                $updated += $persistResult['updated'];
                $tokensUpserted += $persistResult['tokens_upserted'];
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

    /**
     * @return array{inserted:int,updated:int,tokens_upserted:int}
     */
    private function persistMarketPayload(array $marketPayload): array
    {
        $conditionId = (string) ($marketPayload['conditionId'] ?? $marketPayload['condition_id'] ?? '');
        if ($conditionId === '') {
            return [
                'inserted' => 0,
                'updated' => 0,
                'tokens_upserted' => 0,
            ];
        }

        $attributes = [
            'slug' => $marketPayload['slug'] ?? null,
            'title' => $marketPayload['title'] ?? null,
            'category' => $marketPayload['category'] ?? null,
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

        $existing = Market::query()->where('condition_id', $conditionId)->exists();
        $market = Market::query()->updateOrCreate(
            ['condition_id' => $conditionId],
            $attributes
        );

        return [
            'inserted' => $existing ? 0 : 1,
            'updated' => $existing ? 1 : 0,
            'tokens_upserted' => $this->syncMarketTokens($market, $marketPayload),
        ];
    }

    private function fetchActiveMarketByConditionId(string $conditionId): ?array
    {
        $response = Http::baseUrl($this->gammaHost())
            ->timeout((int) config('services.polymarket.timeout_seconds', 15))
            ->acceptJson()
            ->withOptions([
                'verify' => false,
            ])
            ->retry(2, 300)
            ->get('/markets', [
                'active' => 'true',
                'closed' => 'false',
                'condition_ids' => $conditionId,
                'limit' => 5,
                'offset' => 0,
            ]);

        $response->throw();

        $payload = $response->json();
        $markets = is_array($payload) ? $payload : [];

        if (count($markets) === 0) {
            return null;
        }

        foreach ($markets as $market) {
            if (! is_array($market)) {
                continue;
            }

            if ($this->extractConditionId($market) === $conditionId) {
                return $market;
            }
        }

        return null;
    }

    private function fetchMarketByConditionId(string $conditionId): ?array
    {
        $queryCandidates = [
            [
                'condition_ids' => $conditionId,
                'limit' => 5,
                'offset' => 0,
            ],
            [
                'active' => 'true',
                'closed' => 'false',
                'condition_ids' => $conditionId,
                'limit' => 5,
                'offset' => 0,
            ],
            [
                'active' => 'false',
                'closed' => 'true',
                'condition_ids' => $conditionId,
                'limit' => 5,
                'offset' => 0,
            ],
        ];

        foreach ($queryCandidates as $query) {
            $response = Http::baseUrl($this->gammaHost())
                ->timeout((int) config('services.polymarket.timeout_seconds', 15))
                ->acceptJson()
                ->withOptions([
                    'verify' => false,
                ])
                ->retry(2, 300)
                ->get('/markets', $query);

            $response->throw();

            $payload = $response->json();
            $markets = is_array($payload) ? $payload : [];

            foreach ($markets as $market) {
                if (! is_array($market)) {
                    continue;
                }

                if ($this->extractConditionId($market) === $conditionId) {
                    return $market;
                }
            }
        }

        return null;
    }

    private function extractConditionId(array $marketPayload): string
    {
        return trim((string) ($marketPayload['conditionId'] ?? $marketPayload['condition_id'] ?? ''));
    }

    /**
     * @return Collection<int, string>
     */
    private function watchedConditionIds(int $limit): Collection
    {
        return WalletTrade::query()
            ->whereNotNull('condition_id')
            ->where('condition_id', '!=', '')
            ->distinct()
            ->orderByDesc('traded_at')
            ->limit(max(1, $limit))
            ->pluck('condition_id');
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
