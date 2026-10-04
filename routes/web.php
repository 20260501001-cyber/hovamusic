<?php

use App\Http\Controllers\Admin\ReleaseArchiveController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ArtistController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\NotificationController;
use App\Http\Controllers\Panel\PreferencesController;
use App\Http\Controllers\Panel\ReleaseController;
use App\Http\Controllers\Panel\ReleaseRequestController;
use App\Http\Controllers\Panel\UploadController;
use App\Http\Middleware\RestrictAdminIp;
use App\Providers\Filament\AdminPanelProvider;
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

        Route::get('sanatcilar', ArtistController::class)->name('artists');

        Route::get('yayinlar', [ReleaseController::class, 'index'])->name('releases.index');
        Route::post('yayinlar', [ReleaseController::class, 'store'])->middleware('throttle:20,1')->name('releases.store');
        Route::get('yayinlar/{release}', [ReleaseController::class, 'show'])->name('releases.show');
        Route::get('yayinlar/{release}/duzenle/{step?}', [ReleaseController::class, 'edit'])->whereNumber('step')->name('releases.edit');
        Route::delete('yayinlar/{release}', [ReleaseController::class, 'destroy'])->name('releases.destroy');
        Route::post('yayinlar/{release}/talepler', [ReleaseRequestController::class, 'store'])->middleware('throttle:10,1')->name('releases.requests.store');

        Route::get('bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('bildirimler/okundu', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('bildirimler/{notification}', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
        Route::post('bildirimler/{notification}/okundu', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('notifications.read');

        Route::middleware('throttle:uploads')->group(function (): void {
            Route::post('yuklemeler', [UploadController::class, 'store'])->name('uploads.store');
            Route::get('yuklemeler/{upload}', [UploadController::class, 'show'])->name('uploads.show');
            Route::patch('yuklemeler/{upload}', [UploadController::class, 'update'])->name('uploads.update');
            Route::delete('yuklemeler/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
        });
    });

Route::post('goruntuleme/bitir', ImpersonationController::class)->name('impersonation.end');

// Özel diskteki dosyalar: imzalı ve süreli adres, sahibi ya da inceleme yapan admin.
Route::get('medya/{media}', MediaController::class)
    ->middleware(['signed', 'auth:web,admin'])
    ->name('media.show');

// Admin indirmeleri: Filament dışında, aynı admin yolu altında, imzalı ve süreli.
Route::prefix(AdminPanelProvider::path())
    ->middleware([RestrictAdminIp::class, 'auth:admin', 'signed'])
    ->name('admin.')
    ->group(function (): void {
        Route::get('indir/yayin/{release}', ReleaseArchiveController::class)->name('releases.archive');
    });
