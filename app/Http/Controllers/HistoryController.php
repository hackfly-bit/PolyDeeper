<?php

namespace App\Http\Controllers;

use App\Models\ExecutionLog;
use App\Models\Market;
use App\Models\Signal;
use App\Models\Wallet;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): View
    {
        $selectedType = strtolower(trim((string) $request->input('type', 'all')));
        $selectedStatus = strtolower(trim((string) $request->input('status', 'all')));
        $selectedWalletId = (int) $request->integer('wallet_id', 0);
        $search = trim((string) $request->input('q', ''));
        $fromDate = trim((string) $request->input('from', ''));
        $toDate = trim((string) $request->input('to', ''));

        if (! in_array($selectedType, ['all', 'signal', 'execution'], true)) {
            $selectedType = 'all';
        }

        $wallet = $selectedWalletId > 0 ? Wallet::query()->find($selectedWalletId) : null;

        $signalsQuery = Signal::query()
            ->with('wallet:id,name,address')
            ->latest();

        if ($selectedWalletId > 0) {
            $signalsQuery->where('wallet_id', $selectedWalletId);
        }

        if ($selectedStatus === 'buy') {
            $signalsQuery->where('direction', '>', 0);
        }

        if ($selectedStatus === 'sell') {
            $signalsQuery->where('direction', '<', 0);
        }

        if ($search !== '') {
            $searchKeyword = '%'.strtolower($search).'%';
            $signalsQuery->where(function ($query) use ($searchKeyword): void {
                $query->whereRaw('LOWER(COALESCE(market_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(condition_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(token_id, \'\')) LIKE ?', [$searchKeyword]);
            });
        }

        if ($fromDate !== '') {
            $signalsQuery->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $signalsQuery->whereDate('created_at', '<=', $toDate);
        }

        $executionsQuery = ExecutionLog::query()
            ->latest('occurred_at');

        if ($wallet !== null && $wallet->address !== '') {
            $executionsQuery->where('wallet_address', $wallet->address);
        }

        if ($selectedStatus !== 'all' && ! in_array($selectedStatus, ['buy', 'sell'], true)) {
            $executionsQuery->whereRaw('LOWER(COALESCE(status, \'\')) = ?', [$selectedStatus]);
        }

        if ($search !== '') {
            $searchKeyword = '%'.strtolower($search).'%';
            $executionsQuery->where(function ($query) use ($searchKeyword): void {
                $query->whereRaw('LOWER(COALESCE(market_id, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(stage, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(action, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(message, \'\')) LIKE ?', [$searchKeyword])
                    ->orWhereRaw('LOWER(COALESCE(wallet_address, \'\')) LIKE ?', [$searchKeyword]);
            });
        }

        if ($fromDate !== '') {
            $executionsQuery->whereDate('occurred_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $executionsQuery->whereDate('occurred_at', '<=', $toDate);
        }

        $signals = $signalsQuery
            ->with('market:id,condition_id,question,title')
            ->paginate(20, ['*'], 'signals_page')
            ->withQueryString();
        $executions = $executionsQuery
            ->paginate(20, ['*'], 'executions_page')
            ->withQueryString();

        $marketConditionIds = collect()
            ->merge(
                $signals->getCollection()
                    ->pluck('market_id')
                    ->filter()
                    ->values()
            )
            ->merge(
                $signals->getCollection()
                    ->pluck('condition_id')
                    ->filter()
                    ->values()
            )
            ->merge(
                $executions->getCollection()
                    ->pluck('market_id')
                    ->filter()
                    ->values()
            )
            ->unique()
            ->values();

        $marketTitlesByCondition = Market::query()
            ->whereIn('condition_id', $marketConditionIds)
            ->get(['condition_id', 'question'])
            ->mapWithKeys(function (Market $market): array {
                $title = trim((string) ($market->question ?? ''));

                return [$market->condition_id => $title];
            });

        return view('dashboard.history', [
            'pageTitle' => 'History',
            'selectedType' => $selectedType,
            'selectedStatus' => $selectedStatus,
            'selectedWalletId' => $selectedWalletId,
            'search' => $search,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'walletOptions' => Wallet::query()
                ->orderBy('name')
                ->get(['id', 'name', 'address']),
            'signals' => $signals,
            'executions' => $executions,
            'marketTitlesByCondition' => $marketTitlesByCondition,
        ]);
    }
}
