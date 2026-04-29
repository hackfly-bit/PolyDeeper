<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\MarkerController;
use App\Http\Controllers\PolymarketAccountController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SignalController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/positions', [PositionController::class, 'index'])->name('positions');
Route::get('/signals', [SignalController::class, 'index'])->name('signals');
Route::get('/history', [HistoryController::class, 'index'])->name('history');
Route::get('/wallets', [WalletController::class, 'index'])->name('wallets');
Route::get('/markers', [MarkerController::class, 'index'])->name('markers');
Route::post('/wallets', [WalletController::class, 'store'])->name('wallets.store');
Route::put('/wallets/{wallet}', [WalletController::class, 'update'])->name('wallets.update');
Route::post('/wallets/{wallet}/refresh', [WalletController::class, 'refresh'])->name('wallets.refresh');
Route::delete('/wallets/{wallet}', [WalletController::class, 'destroy'])->name('wallets.destroy');
Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
Route::post('/settings/polymarket/select-account', [SettingsController::class, 'selectPolymarketAccount'])->name('settings.polymarket.select-account');
Route::get('/settings/polymarket/accounts', [PolymarketAccountController::class, 'index'])->name('settings.polymarket.accounts.index');
Route::post('/settings/polymarket/accounts', [PolymarketAccountController::class, 'store'])->name('settings.polymarket.accounts.store');
Route::get('/settings/polymarket/accounts/{account}', [PolymarketAccountController::class, 'show'])->name('settings.polymarket.accounts.show');
Route::put('/settings/polymarket/accounts/{account}', [PolymarketAccountController::class, 'update'])->name('settings.polymarket.accounts.update');
Route::post('/settings/polymarket/accounts/refresh-balances', [PolymarketAccountController::class, 'refreshAllBalances'])->name('settings.polymarket.accounts.refresh-balances');
Route::post('/settings/polymarket/accounts/{account}/validate', [PolymarketAccountController::class, 'validateCredentials'])->name('settings.polymarket.accounts.validate');
Route::post('/settings/polymarket/accounts/{account}/rotate', [PolymarketAccountController::class, 'rotateCredentials'])->name('settings.polymarket.accounts.rotate');
Route::post('/settings/polymarket/accounts/{account}/revoke', [PolymarketAccountController::class, 'revokeCredentials'])->name('settings.polymarket.accounts.revoke');
Route::post('/settings/polymarket/accounts/{account}/disable-trading', [PolymarketAccountController::class, 'disableTrading'])->name('settings.polymarket.accounts.disable-trading');
Route::post('/settings/polymarket/accounts/{account}/enable-trading', [PolymarketAccountController::class, 'enableTrading'])->name('settings.polymarket.accounts.enable-trading');
Route::post('/settings/polymarket/accounts/{account}/refresh-balance', [PolymarketAccountController::class, 'refreshBalance'])->name('settings.polymarket.accounts.refresh-balance');
Route::get('/settings/polymarket/accounts/{account}/health', [PolymarketAccountController::class, 'health'])->name('settings.polymarket.accounts.health');
