<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RestrictAdminIp;
use App\Http\Middleware\SecurityHeaders;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(self::path())
            ->authGuard('admin')
            ->login()
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true)
            ->brandName('Hova Music')
            ->colors([
                'primary' => Color::hex('#4C3BFF'),
            ])
            ->font('Archivo', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                RestrictAdminIp::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SecurityHeaders::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * ADMIN_PATH boşsa uygulama anahtarından türetilen, tahmin edilemeyen bir yol kullanılır.
     */
    public static function path(): string
    {
        $configured = trim((string) config('hova.admin.path'), '/');

        return $configured !== '' ? $configured : 'yonetim-'.substr(hash('sha256', (string) config('app.key')), 0, 20);
    }
}
