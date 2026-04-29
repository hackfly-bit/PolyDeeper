<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PolymarketAccountController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/positions', [DashboardController::class, 'positions'])->name('positions');
Route::get('/signals', [DashboardController::class, 'signals'])->name('signals');
Route::get('/wallets', [DashboardController::class, 'wallets'])->name('wallets');
Route::get('/markers', [DashboardController::class, 'markers'])->name('markers');
Route::post('/wallets', [DashboardController::class, 'storeWallet'])->name('wallets.store');
Route::put('/wallets/{wallet}', [DashboardController::class, 'updateWallet'])->name('wallets.update');
Route::post('/wallets/{wallet}/refresh', [DashboardController::class, 'refreshWallet'])->name('wallets.refresh');
Route::delete('/wallets/{wallet}', [DashboardController::class, 'destroyWallet'])->name('wallets.destroy');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
Route::post('/settings/polymarket/select-account', [DashboardController::class, 'selectPolymarketAccount'])->name('settings.polymarket.select-account');
Route::get('/settings/polymarket/accounts', [PolymarketAccountController::class, 'index'])->name('settings.polymarket.accounts.index');
Route::post('/settings/polymarket/accounts', [PolymarketAccountController::class, 'store'])->name('settings.polymarket.accounts.store');
Route::get('/settings/polymarket/accounts/{account}', [PolymarketAccountController::class, 'show'])->name('settings.polymarket.accounts.show');
Route::put('/settings/polymarket/accounts/{account}', [PolymarketAccountController::class, 'update'])->name('settings.polymarket.accounts.update');
Route::post('/settings/polymarket/accounts/{account}/validate', [PolymarketAccountController::class, 'validateCredentials'])->name('settings.polymarket.accounts.validate');
Route::post('/settings/polymarket/accounts/{account}/rotate', [PolymarketAccountController::class, 'rotateCredentials'])->name('settings.polymarket.accounts.rotate');
Route::post('/settings/polymarket/accounts/{account}/revoke', [PolymarketAccountController::class, 'revokeCredentials'])->name('settings.polymarket.accounts.revoke');
Route::post('/settings/polymarket/accounts/{account}/disable-trading', [PolymarketAccountController::class, 'disableTrading'])->name('settings.polymarket.accounts.disable-trading');
Route::post('/settings/polymarket/accounts/{account}/enable-trading', [PolymarketAccountController::class, 'enableTrading'])->name('settings.polymarket.accounts.enable-trading');
Route::get('/settings/polymarket/accounts/{account}/health', [PolymarketAccountController::class, 'health'])->name('settings.polymarket.accounts.health');
