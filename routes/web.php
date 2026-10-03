<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\PreferencesController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('yasal/{slug}', LegalPageController::class)->name('legal.show');

Route::middleware(['auth', 'verified', 'account.active'])
    ->prefix('panel')
    ->name('panel.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('hesap', [AccountController::class, 'show'])->name('account');
        Route::put('hesap/tercihler', [PreferencesController::class, 'update'])->name('account.preferences');
    });
