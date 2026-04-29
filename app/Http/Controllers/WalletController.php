<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Services\Polymarket\PolymarketWalletStatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class WalletController extends Controller
{
    public function index(): View
    {
        return view('dashboard.wallets', [
            'pageTitle' => 'Tracked Wallets',
            'wallets' => Wallet::query()->latest('last_active')->paginate(20),
        ]);
    }

    public function store(
        Request $request,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse {
        if ($request->filled('address')) {
            $request->merge([
                'address' => $polymarketWalletStatsService->normalizeAddress((string) $request->input('address')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255', 'unique:wallets,address'],
        ]);

        try {
            Wallet::query()->create(
                $polymarketWalletStatsService->payloadForWallet(
                    $validated['name'],
                    $validated['address'],
                )
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        Wallet $wallet,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse {
        if ($request->filled('address')) {
            $request->merge([
                'address' => $polymarketWalletStatsService->normalizeAddress((string) $request->input('address')),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => [
                'required',
                'string',
                'max:255',
                Rule::unique('wallets', 'address')->ignore($wallet->id),
            ],
        ]);

        try {
            $wallet->update(
                $polymarketWalletStatsService->payloadForWallet(
                    $validated['name'],
                    $validated['address'],
                )
            );
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil diperbarui.');
    }

    public function refresh(
        Wallet $wallet,
        PolymarketWalletStatsService $polymarketWalletStatsService
    ): RedirectResponse {
        try {
            $polymarketWalletStatsService->syncWallet($wallet);
        } catch (Throwable $exception) {
            return redirect()
                ->route('wallets')
                ->withErrors(['wallet_sync' => $exception->getMessage()]);
        }

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil diperbarui dari Polymarket.');
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        $wallet->delete();

        return redirect()
            ->route('wallets')
            ->with('wallet_success', 'Wallet berhasil dihapus.');
    }
}
