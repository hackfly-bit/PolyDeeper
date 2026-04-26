<?php

namespace App\Services\Polymarket;

use App\Models\Wallet;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PolymarketWalletStatsService
{
    public function __construct(
        private HttpFactory $http,
    ) {
    }

    /**
     * @return array{name: string, address: string, weight: float, pnl: float, win_rate: float, roi: float, last_active: ?Carbon}
     */
    public function payloadForWallet(string $name, string $address): array
    {
        $normalizedAddress = $this->normalizeAddress($address);
        $stats = $this->fetchStats($normalizedAddress);

        return [
            'name' => trim($name),
            'address' => $normalizedAddress,
            'weight' => $stats['weight'],
            'pnl' => $stats['pnl'],
            'win_rate' => $stats['win_rate'],
            'roi' => $stats['roi'],
            'last_active' => $stats['last_active'],
        ];
    }

    public function syncWallet(Wallet $wallet): Wallet
    {
        $wallet->update($this->payloadForWallet($wallet->name, $wallet->address));

        return $wallet->refresh();
    }

    /**
     * @return array{weight: float, pnl: float, win_rate: float, roi: float, last_active: ?Carbon}
     */
    public function fetchStats(string $address): array
    {
        try {
            $trades = $this->fetchRows('/trades', ['user' => $address]);
            $activity = $this->fetchRows('/activity', ['user' => $address]);
            $valueRows = $this->fetchRows('/value', ['user' => $address]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Gagal terhubung ke Polymarket API. Coba lagi beberapa saat lagi.', 0, $exception);
        } catch (Throwable $exception) {
            throw new RuntimeException('Gagal mengambil data wallet dari Polymarket API.', 0, $exception);
        }

        $buyCost = 0.0;
        $sellProceeds = 0.0;
        $positionSummaries = [];
        $lastActiveTimestamp = null;
        $realizedGain = 0.0;
        $realizedLoss = 0.0;

        usort($trades, function (array $left, array $right): int {
            return ((int) ($left['timestamp'] ?? 0)) <=> ((int) ($right['timestamp'] ?? 0));
        });

        foreach ($trades as $trade) {
            $side = Str::upper((string) ($trade['side'] ?? ''));
            $assetKey = (string) ($trade['asset'] ?? ($trade['conditionId'] ?? 'unknown'));
            $size = $this->toFloat($trade['size'] ?? null);
            $notional = $this->resolveNotional($trade);

            if (! isset($positionSummaries[$assetKey])) {
                $positionSummaries[$assetKey] = [
                    'open_size' => 0.0,
                    'open_cost' => 0.0,
                ];
            }

            if ($side === 'BUY') {
                $buyCost += $notional;
                $positionSummaries[$assetKey]['open_size'] += $size;
                $positionSummaries[$assetKey]['open_cost'] += $notional;
            }

            if ($side === 'SELL') {
                $sellProceeds += $notional;

                $openSize = $positionSummaries[$assetKey]['open_size'];
                $openCost = $positionSummaries[$assetKey]['open_cost'];
                $matchedSize = min($size, $openSize);

                if ($matchedSize > 0) {
                    $averageOpenCost = $openCost / max($openSize, 0.000001);
                    $matchedCost = $averageOpenCost * $matchedSize;
                    $matchedProceeds = $notional * ($matchedSize / max($size, 0.000001));
                    $realizedPnl = $matchedProceeds - $matchedCost;

                    if ($realizedPnl >= 0) {
                        $realizedGain += $realizedPnl;
                    } else {
                        $realizedLoss += abs($realizedPnl);
                    }

                    $positionSummaries[$assetKey]['open_size'] = max($openSize - $matchedSize, 0.0);
                    $positionSummaries[$assetKey]['open_cost'] = max($openCost - $matchedCost, 0.0);
                }
            }

            $lastActiveTimestamp = max(
                $lastActiveTimestamp ?? 0,
                (int) ($trade['timestamp'] ?? 0),
            );
        }

        foreach ($activity as $activityItem) {
            $lastActiveTimestamp = max(
                $lastActiveTimestamp ?? 0,
                (int) ($activityItem['timestamp'] ?? 0),
            );
        }

        $currentValue = collect($valueRows)
            ->sum(fn (array $row): float => $this->toFloat($row['value'] ?? null));

        $pnl = round($currentValue + $sellProceeds - $buyCost, 2);
        $roi = $buyCost > 0
            ? round(($pnl / $buyCost) * 100, 2)
            : 0.0;

        $totalClosedPnl = $realizedGain + $realizedLoss;

        $winRate = $totalClosedPnl > 0
            ? round(($realizedGain / $totalClosedPnl) * 100, 2)
            : 0.0;

        $lastActive = $lastActiveTimestamp
            ? Carbon::createFromTimestampUTC($lastActiveTimestamp)->setTimezone(config('app.timezone'))
            : null;

        return [
            'weight' => $this->calculateWeight($winRate, $roi, $lastActive),
            'pnl' => $pnl,
            'win_rate' => $winRate,
            'roi' => $roi,
            'last_active' => $lastActive,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $path, array $query): array
    {
        $response = $this->request()
            ->get($path, $query)
            ->throw();

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl((string) Config::get('services.polymarket.data_host', 'https://data-api.polymarket.com'))
            ->acceptJson()
            ->timeout(15)
            ->withOptions([
                'verify' => false,
            ])
            ->retry(2, 300, throw: false);
    }

    public function normalizeAddress(string $address): string
    {
        $normalized = trim($address);

        if ($normalized === '') {
            throw new RuntimeException('Address wallet wajib diisi.');
        }

        if (! Str::startsWith(Str::lower($normalized), '0x')) {
            $normalized = '0x'.ltrim($normalized, '0x');
        }

        return Str::lower($normalized);
    }

    /**
     * @param  array<string, mixed>  $trade
     */
    private function resolveNotional(array $trade): float
    {
        $usdcSize = $this->toFloat($trade['usdcSize'] ?? null);

        if ($usdcSize > 0) {
            return $usdcSize;
        }

        return $this->toFloat($trade['price'] ?? null) * $this->toFloat($trade['size'] ?? null);
    }

    private function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function calculateWeight(float $winRate, float $roi, ?CarbonInterface $lastActive): float
    {
        $winRateScore = min(max($winRate / 100, 0), 1);
        $roiScore = min(max(($roi + 100) / 200, 0), 1);
        $recencyScore = $this->recencyScore($lastActive);

        return round(min((0.5 * $winRateScore) + (0.3 * $roiScore) + (0.2 * $recencyScore), 1), 4);
    }

    private function recencyScore(?CarbonInterface $lastActive): float
    {
        if ($lastActive === null) {
            return 0.1;
        }

        $daysSinceLastActive = $lastActive->diffInDays(now());

        return match (true) {
            $daysSinceLastActive <= 1 => 1.0,
            $daysSinceLastActive <= 7 => 0.9,
            $daysSinceLastActive <= 30 => 0.7,
            $daysSinceLastActive <= 90 => 0.45,
            default => 0.2,
        };
    }
}
