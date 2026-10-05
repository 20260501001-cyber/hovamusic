<?php

use App\Http\Controllers\Admin\ReleaseArchiveController;
use App\Http\Controllers\Admin\TaxFormDownloadController;
use App\Http\Controllers\CookieConsentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\LegalPageController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Panel\AccountController;
use App\Http\Controllers\Panel\ArtistController;
use App\Http\Controllers\Panel\BillingProfileController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\EarningsController;
use App\Http\Controllers\Panel\NotificationController;
use App\Http\Controllers\Panel\PayoutMethodController;
use App\Http\Controllers\Panel\PlanController;
use App\Http\Controllers\Panel\PreferencesController;
use App\Http\Controllers\Panel\PrivacyController;
use App\Http\Controllers\Panel\ReleaseController;
use App\Http\Controllers\Panel\ReleaseRequestController;
use App\Http\Controllers\Panel\TaxFormController;
use App\Http\Controllers\Panel\UploadController;
use App\Http\Controllers\Panel\WithdrawalController;
use App\Http\Controllers\Webhooks\PolarWebhookController;
use App\Http\Middleware\RestrictAdminIp;
use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Ödeme sağlayıcısı webhook'u: oturum ve CSRF yok; imza denetimi denetleyicide.
Route::post('webhooks/polar', PolarWebhookController::class)
    ->withoutMiddleware(['web'])
    ->middleware('throttle:120,1')
    ->name('webhooks.polar');
Route::get('yasal/{slug}', LegalPageController::class)->name('legal.show');
Route::post('cerez-tercihleri', CookieConsentController::class)->middleware('throttle:20,1')->name('cookies.store');

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

        Route::get('plan', [PlanController::class, 'index'])->name('plans.index');
        Route::get('plan/siparisler', [PlanController::class, 'orders'])->name('plans.orders');
        Route::post('plan/yonet', [PlanController::class, 'portal'])->middleware('throttle:10,1')->name('plans.portal');
        Route::get('plan/odeme/{checkout}', [PlanController::class, 'processing'])->name('plans.processing');
        Route::get('plan/{plan}', [PlanController::class, 'confirm'])->name('plans.confirm');
        Route::post('plan/{plan}/odeme', [PlanController::class, 'checkout'])->middleware('throttle:10,1')->name('plans.checkout');

        Route::get('kazanclar', [EarningsController::class, 'index'])->name('earnings.index');
        Route::get('kazanclar/csv', [EarningsController::class, 'export'])->middleware('throttle:10,1')->name('earnings.export');
        Route::get('para-cekme', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('para-cekme', [WithdrawalController::class, 'store'])->middleware('throttle:5,1')->name('withdrawals.store');
        Route::get('hesap/fatura-bilgileri', [BillingProfileController::class, 'edit'])->name('finance.profile');
        Route::put('hesap/fatura-bilgileri', [BillingProfileController::class, 'update'])->middleware('throttle:10,1')->name('finance.profile.update');
        Route::get('hesap/odeme-bilgileri', [PayoutMethodController::class, 'edit'])->name('finance.payout');
        Route::put('hesap/odeme-bilgileri', [PayoutMethodController::class, 'update'])->middleware('throttle:5,1')->name('finance.payout.update');
        Route::get('hesap/vergi-formu', [TaxFormController::class, 'create'])->name('tax-form.create');
        Route::post('hesap/vergi-formu', [TaxFormController::class, 'store'])->middleware('throttle:5,1')->name('tax-form.store');
        Route::get('hesap/vergi-formu/{taxForm}/pdf', [TaxFormController::class, 'download'])->name('tax-form.download');

        Route::post('hesap/veri-talepleri', [PrivacyController::class, 'store'])->middleware('throttle:5,1')->name('privacy.store');
        Route::get('hesap/veri-talepleri/{dataRequest}/indir', [PrivacyController::class, 'download'])->middleware('signed')->name('privacy.download');

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
        Route::get('indir/vergi-formu/{taxForm}', TaxFormDownloadController::class)->name('tax-forms.download');
    });
