<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/positions', [DashboardController::class, 'positions'])->name('positions');
Route::get('/signals', [DashboardController::class, 'signals'])->name('signals');
Route::get('/wallets', [DashboardController::class, 'wallets'])->name('wallets');
Route::post('/wallets', [DashboardController::class, 'storeWallet'])->name('wallets.store');
Route::put('/wallets/{wallet}', [DashboardController::class, 'updateWallet'])->name('wallets.update');
Route::delete('/wallets/{wallet}', [DashboardController::class, 'destroyWallet'])->name('wallets.destroy');
Route::get('/settings', [DashboardController::class, 'settings'])->name('settings');
