<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Models\Wallet;
use App\Models\WalletTrade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarkerController extends Controller
{
    public function index(Request $request): View
    {
        $selectedWalletId = (int) $request->integer('wallet_id', 0);
        $selectedStatus = strtolower(trim((string) $request->input('status', 'all')));
        $searchTitle = trim((string) $request->input('q', ''));

        if (! in_array($selectedStatus, ['all', 'open', 'closed'], true)) {
            $selectedStatus = 'all';
        }

        $markerQuery = WalletTrade::query()
            ->leftJoin('markets', 'markets.condition_id', '=', 'wallet_trades.condition_id')
            ->select([
                'wallet_trades.condition_id',
                DB::raw('MAX(wallet_trades.traded_at) as last_traded_at'),
                DB::raw('MIN(CASE WHEN markets.closed = true OR (markets.end_date IS NOT NULL AND markets.end_date < CURRENT_TIMESTAMP) THEN 1 ELSE 0 END) as status_sort'),
            ])
            ->whereNotNull('wallet_trades.condition_id')
            ->where('wallet_trades.condition_id', '!=', '');

        if ($selectedWalletId > 0) {
            $markerQuery->where('wallet_trades.wallet_id', $selectedWalletId);
        }

        if ($selectedStatus === 'open') {
            $markerQuery->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($statusQuery): void {
                        $statusQuery->where('markets.closed', false)
                            ->orWhereNull('markets.closed');
                    })
                    ->where(function ($dateQuery): void {
                        $dateQuery->whereNull('markets.end_date')
                            ->orWhere('markets.end_date', '>=', now());
                    });
            });
        }

        if ($selectedStatus === 'closed') {
            $markerQuery->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($statusQuery): void {
                        $statusQuery->where('markets.closed', true)
                            ->orWhere('markets.end_date', '<', now());
                    });
            });
        }

        if ($searchTitle !== '') {
            $searchKeyword = '%'.strtolower($searchTitle).'%';

            $markerQuery->whereExists(function ($query) use ($searchKeyword): void {
                $query->select(DB::raw(1))
                    ->from('markets')
                    ->whereColumn('markets.condition_id', 'wallet_trades.condition_id')
                    ->where(function ($titleQuery) use ($searchKeyword): void {
                        $titleQuery->whereRaw('LOWER(COALESCE(markets.question, \'\')) LIKE ?', [$searchKeyword])
                            ->orWhereRaw('LOWER(COALESCE(markets.title, \'\')) LIKE ?', [$searchKeyword]);
                    });
            });
        }

        $markerRows = $markerQuery
            ->groupBy('wallet_trades.condition_id')
            ->orderBy('status_sort')
            ->orderByDesc('last_traded_at')
            ->paginate(20)
            ->withQueryString();

        $conditionIds = $markerRows->getCollection()
            ->pluck('condition_id')
            ->filter()
            ->values();

        $marketsByCondition = Market::query()
            ->whereIn('condition_id', $conditionIds)
            ->get()
            ->keyBy('condition_id');

        $walletNameExpression = "CASE WHEN wallets.name != '' THEN wallets.name ELSE wallets.address END";
        $walletNamesAggregate = DB::connection()->getDriverName() === 'pgsql'
            ? "STRING_AGG(DISTINCT {$walletNameExpression}, ',')"
            : "GROUP_CONCAT(DISTINCT {$walletNameExpression})";

        $walletAggregateByCondition = WalletTrade::query()
            ->join('wallets', 'wallets.id', '=', 'wallet_trades.wallet_id')
            ->whereIn('wallet_trades.condition_id', $conditionIds)
            ->select([
                'wallet_trades.condition_id',
                DB::raw("{$walletNamesAggregate} AS wallet_names"),
                DB::raw('COUNT(DISTINCT wallet_trades.wallet_id) AS wallet_count'),
            ])
            ->groupBy('wallet_trades.condition_id')
            ->get()
            ->keyBy('condition_id');

        $markerRows->setCollection(
            $markerRows->getCollection()->map(function (WalletTrade $walletTrade) use ($marketsByCondition, $walletAggregateByCondition): array {
                $market = $marketsByCondition->get($walletTrade->condition_id);
                $marketPayload = $market?->raw_payload ?? [];
                $walletAggregate = $walletAggregateByCondition->get($walletTrade->condition_id);
                $walletNames = collect(explode(',', (string) ($walletAggregate->wallet_names ?? '')))
                    ->map(fn (string $name): string => trim($name))
                    ->filter()
                    ->values();
                $eventSlug = (string) ($market->slug ?? '');
                $category = $this->extractMarketCategory($marketPayload);
                $timeRemaining = $market?->end_date !== null
                    ? ($market->end_date->isPast() ? 'Sudah selesai' : $market->end_date->diffForHumans(now(), [
                        'syntax' => Carbon::DIFF_RELATIVE_TO_NOW,
                        'parts' => 2,
                    ]))
                    : '-';
                $isClosed = ($market?->closed ?? false) || ($market?->end_date?->isPast() ?? false);

                return [
                    'condition_id' => $walletTrade->condition_id,
                    'title' => (string) ($market?->question ?? 'Market tanpa judul'),
                    'category' => $category,
                    'status' => $isClosed ? 'Closed' : 'Open',
                    'wallet_names' => $walletNames->join(', '),
                    'wallet_count' => (int) ($walletAggregate->wallet_count ?? 0),
                    'market_url' => $eventSlug !== '' ? 'https://polymarket.com/event/'.$eventSlug : null,
                    'detail' => [
                        'volume' => $this->extractMarketVolume($marketPayload),
                        'time_remaining' => $timeRemaining,
                        'rules' => $this->extractMarketRules($marketPayload, $market?->description),
                        'context' => $this->extractMarketContext($marketPayload),
                    ],
                ];
            })
        );

        return view('dashboard.markers', [
            'pageTitle' => 'Marker',
            'markers' => $markerRows,
            'walletOptions' => Wallet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'address']),
            'selectedWalletId' => $selectedWalletId,
            'selectedStatus' => $selectedStatus,
            'searchTitle' => $searchTitle,
        ]);
    }

    private function extractMarketCategory(array $marketPayload): string
    {
        $category = data_get($marketPayload, 'category')
            ?? data_get($marketPayload, 'groupItemTitle')
            ?? data_get($marketPayload, 'questionCategory')
            ?? data_get($marketPayload, 'event.title')
            ?? '-';

        return trim((string) $category) !== '' ? (string) $category : '-';
    }

    private function extractMarketVolume(array $marketPayload): string
    {
        $volume = data_get($marketPayload, 'volume')
            ?? data_get($marketPayload, 'volumeNum')
            ?? data_get($marketPayload, 'volume24hr')
            ?? data_get($marketPayload, 'liquidityNum');

        if ($volume === null || $volume === '') {
            return '-';
        }

        if (is_numeric($volume)) {
            return '$'.number_format((float) $volume, 2);
        }

        return (string) $volume;
    }

    private function extractMarketRules(array $marketPayload, ?string $description): string
    {
        $rules = data_get($marketPayload, 'rules')
            ?? data_get($marketPayload, 'marketRules')
            ?? $description
            ?? '-';

        return Str::limit(trim((string) $rules), 320, '...');
    }

    private function extractMarketContext(array $marketPayload): string
    {
        $context = data_get($marketPayload, 'context')
            ?? data_get($marketPayload, 'description')
            ?? data_get($marketPayload, 'event.description')
            ?? data_get($marketPayload, 'event.title')
            ?? '-';

        return Str::limit(trim((string) $context), 320, '...');
    }
}
