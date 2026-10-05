<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialsAvatarProvider;
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
            // Tasarım sistemindeki accent tonları (600 = accent). Color::hex() paleti
            // açık türettiği için Filament butonlarda soluk mor + koyu yazı seçiyordu.
            // 500, açık yazıyla kalacak kadar koyu tutuldu; Filament butonun hover
            // tonu olarak onu kullanır.
            ->colors([
                'primary' => [
                    50 => '#F4F3FF',
                    100 => '#ECEBFF',
                    200 => '#D9D6FF',
                    300 => '#C9C5FF',
                    400 => '#A29BFF',
                    500 => '#6354FF',
                    600 => '#4C3BFF',
                    700 => '#3E2EF2',
                    800 => '#2F22B8',
                    900 => '#231A85',
                    950 => '#1D1A4A',
                ],
            ])
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->font('Archivo', provider: LocalFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true)
            ->navigationGroups(['İnceleme', 'Kullanıcılar', 'Satış', 'Finans', 'İçerik', 'Katalog', 'Sistem'])
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
