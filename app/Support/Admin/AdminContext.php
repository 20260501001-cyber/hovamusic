<?php

namespace App\Support\Admin;

use App\Providers\Filament\AdminPanelProvider;
use Illuminate\Http\Request;

/**
 * İsteğin admin paneli adına yapılıp yapılmadığını söyler. Aynı tarayıcıda hem admin
 * hem kullanıcı oturumu açık olabilir; kullanıcı panelindeki işlemler admin işlemi
 * sayılmaz. Livewire güncellemeleri tek bir adrese gittiği için hangi sayfadan
 * geldikleri Referer başlığından anlaşılır.
 */
class AdminContext
{
    public static function active(?Request $request = null): bool
    {
        if (! auth('admin')->check()) {
            return false;
        }

        $request ??= request();

        if ($request->is('panel', 'panel/*')) {
            return false;
        }

        if (self::isLivewireUpdate($request)) {
            return ! self::refererIsUserPanel($request);
        }

        return true;
    }

    public static function isAdminRequest(Request $request): bool
    {
        $path = AdminPanelProvider::path();

        if ($request->is($path, $path.'/*')) {
            return true;
        }

        return self::isLivewireUpdate($request) && str_starts_with(self::refererPath($request), $path);
    }

    private static function isLivewireUpdate(Request $request): bool
    {
        return $request->hasHeader('X-Livewire');
    }

    private static function refererIsUserPanel(Request $request): bool
    {
        $path = self::refererPath($request);

        return $path === 'panel' || str_starts_with($path, 'panel/');
    }

    private static function refererPath(Request $request): string
    {
        return ltrim((string) parse_url((string) $request->headers->get('referer'), PHP_URL_PATH), '/');
    }
}
