<?php

namespace App\Http\Controllers;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountService;
use App\Services\Polymarket\PolymarketCredentialService;
use App\Services\Polymarket\PolymarketService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PolymarketAccountController extends Controller
{
    public function index(PolymarketAccountService $accountService): View
    {
        return view('dashboard.polymarket-accounts.index', [
            'pageTitle' => 'Polymarket Accounts',
            'accounts' => $accountService->paginate(),
        ]);
    }

    public function show(PolymarketAccount $account): View
    {
        $metrics = app(PolymarketAccountService::class)->metrics($account);

        return view('dashboard.polymarket-accounts.show', [
            'pageTitle' => 'Polymarket Account Detail',
            'account' => $account,
            'metrics' => $metrics,
        ]);
    }

    public function store(Request $request, PolymarketAccountService $accountService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wallet_address' => ['required', 'string', 'max:255'],
            'funder_address' => ['nullable', 'string', 'max:255'],
            'signature_type' => ['required', 'integer', 'in:0,1,2'],
            'env_key_name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'risk_profile' => ['nullable', 'string', 'in:conservative,standard,aggressive'],
            'max_exposure_usd' => ['nullable', 'numeric', 'min:0'],
            'max_order_size' => ['nullable', 'numeric', 'min:0'],
            'max_open_positions' => ['nullable', 'integer', 'min:0'],
            'max_open_positions_per_market' => ['nullable', 'integer', 'min:0'],
            'max_order_size_in_usd' => ['nullable', 'numeric', 'min:0'],
            'daily_limit_mode' => ['nullable', 'string', 'in:count,usd'],
            'max_daily_loss_position' => ['nullable', 'numeric', 'min:0'],
            'max_daily_win_position' => ['nullable', 'numeric', 'min:0'],
            'cooldown_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $accountService->create($validated);

        return redirect()
            ->route('settings.polymarket.accounts.index')
            ->with('account_success', 'Polymarket account berhasil dibuat.');
    }

    public function update(Request $request, PolymarketAccount $account, PolymarketAccountService $accountService): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'wallet_address' => ['required', 'string', 'max:255'],
            'funder_address' => ['nullable', 'string', 'max:255'],
            'signature_type' => ['required', 'integer', 'in:0,1,2'],
            'env_key_name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'risk_profile' => ['nullable', 'string', 'in:conservative,standard,aggressive'],
            'max_exposure_usd' => ['nullable', 'numeric', 'min:0'],
            'max_order_size' => ['nullable', 'numeric', 'min:0'],
            'max_open_positions' => ['nullable', 'integer', 'min:0'],
            'max_open_positions_per_market' => ['nullable', 'integer', 'min:0'],
            'max_order_size_in_usd' => ['nullable', 'numeric', 'min:0'],
            'daily_limit_mode' => ['nullable', 'string', 'in:count,usd'],
            'max_daily_loss_position' => ['nullable', 'numeric', 'min:0'],
            'max_daily_win_position' => ['nullable', 'numeric', 'min:0'],
            'cooldown_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $accountService->update($account, $validated);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Polymarket account berhasil diperbarui.');
    }

    public function validateCredentials(PolymarketAccount $account, PolymarketCredentialService $credentialService): RedirectResponse
    {
        try {
            $credentialService->ensureSignerPrivateKeyExists($account);
            $result = $credentialService->validateCredentials($account);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('settings.polymarket.accounts.show', $account)
                ->with('account_error', $exception->getMessage());
        }

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with($result['ok'] ? 'account_success' : 'account_error', $result['message']);
    }

    public function rotateCredentials(PolymarketAccount $account, PolymarketCredentialService $credentialService): RedirectResponse
    {
        $credentialService->rotateCredentials($account);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Status credential diubah menjadi needs rotation.');
    }

    public function revokeCredentials(PolymarketAccount $account, PolymarketCredentialService $credentialService): RedirectResponse
    {
        $credentialService->revokeCredentials($account);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Credential account berhasil direvoke.');
    }

    public function disableTrading(PolymarketAccount $account, PolymarketAccountService $accountService): RedirectResponse
    {
        $accountService->disableTrading($account);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Trading untuk account ini dinonaktifkan.');
    }

    public function enableTrading(PolymarketAccount $account, PolymarketAccountService $accountService): RedirectResponse
    {
        $accountService->enableTrading($account);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Trading untuk account ini diaktifkan.');
    }

    public function refreshBalance(
        PolymarketAccount $account,
        PolymarketService $polymarketService,
        PolymarketAccountService $accountService
    ): RedirectResponse {
        try {
            $result = $polymarketService->fetchBalance($account);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('settings.polymarket.accounts.show', $account)
                ->with('account_error', $exception->getMessage());
        }

        if (! ($result['ok'] ?? false)) {
            return redirect()
                ->route('settings.polymarket.accounts.show', $account)
                ->with('account_error', $result['error'] ?? 'Gagal mengambil saldo account.');
        }

        $updatedAccount = $accountService->refreshStoredBalance($account, (float) ($result['balance_usd'] ?? 0));

        return redirect()
            ->route('settings.polymarket.accounts.show', $updatedAccount)
            ->with('account_success', 'Saldo account berhasil diperbarui.');
    }

    public function refreshAllBalances(
        PolymarketService $polymarketService,
        PolymarketAccountService $accountService
    ): RedirectResponse {
        $accounts = PolymarketAccount::query()
            ->where('is_active', true)
            ->whereIn('credential_status', ['active', 'needs_rotation'])
            ->get();

        $refreshedCount = 0;
        $failedCount = 0;

        foreach ($accounts as $account) {
            try {
                $result = $polymarketService->fetchBalance($account);
                if (! ($result['ok'] ?? false)) {
                    $failedCount++;

                    continue;
                }

                $accountService->refreshStoredBalance($account, (float) ($result['balance_usd'] ?? 0));
                $refreshedCount++;
            } catch (RuntimeException $exception) {
                $failedCount++;
            }
        }

        return redirect()
            ->route('dashboard')
            ->with(
                'dashboard_success',
                sprintf('Refresh saldo selesai. Berhasil: %d account, gagal: %d account.', $refreshedCount, $failedCount)
            );
    }

    public function health(PolymarketAccount $account): JsonResponse
    {
        return response()->json([
            'account_id' => $account->id,
            'credential_status' => $account->credential_status,
            'last_validated_at' => $account->last_validated_at?->toISOString(),
            'last_error_code' => $account->last_error_code,
            'is_active' => $account->is_active,
            'last_balance_usd' => $account->last_balance_usd,
            'last_balance_refreshed_at' => $account->last_balance_refreshed_at?->toISOString(),
        ]);
    }
}
