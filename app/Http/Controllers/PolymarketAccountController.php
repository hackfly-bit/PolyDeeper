<?php

namespace App\Http\Controllers;

use App\Models\PolymarketAccount;
use App\Services\Polymarket\PolymarketAccountService;
use App\Services\Polymarket\PolymarketCredentialService;
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
            'wallet_address' => ['nullable', 'string', 'max:255'],
            'funder_address' => ['nullable', 'string', 'max:255'],
            'signature_type' => ['required', 'integer', 'in:0,1,2'],
            'vault_key_ref' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'risk_profile' => ['nullable', 'string', 'in:conservative,standard,aggressive'],
            'max_exposure_usd' => ['nullable', 'numeric', 'min:0'],
            'max_order_size' => ['nullable', 'numeric', 'min:0'],
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
            'wallet_address' => ['nullable', 'string', 'max:255'],
            'funder_address' => ['nullable', 'string', 'max:255'],
            'signature_type' => ['required', 'integer', 'in:0,1,2'],
            'env_key_name' => ['nullable', 'string', 'max:255'],
            'vault_key_ref' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'risk_profile' => ['nullable', 'string', 'in:conservative,standard,aggressive'],
            'max_exposure_usd' => ['nullable', 'numeric', 'min:0'],
            'max_order_size' => ['nullable', 'numeric', 'min:0'],
            'cooldown_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $accountService->update($account, $validated);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Polymarket account berhasil diperbarui.');
    }

    public function storeCredentials(
        Request $request,
        PolymarketAccount $account,
        PolymarketCredentialService $credentialService
    ): RedirectResponse {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'max:255'],
            'api_secret' => ['required', 'string', 'max:255'],
            'api_passphrase' => ['required', 'string', 'max:255'],
        ]);

        $credentialService->generateOrStoreCredentials($account, $validated);

        return redirect()
            ->route('settings.polymarket.accounts.show', $account)
            ->with('account_success', 'Kredensial account berhasil disimpan.');
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

    public function health(PolymarketAccount $account): JsonResponse
    {
        return response()->json([
            'account_id' => $account->id,
            'credential_status' => $account->credential_status,
            'last_validated_at' => $account->last_validated_at?->toISOString(),
            'last_error_code' => $account->last_error_code,
            'is_active' => $account->is_active,
        ]);
    }
}
