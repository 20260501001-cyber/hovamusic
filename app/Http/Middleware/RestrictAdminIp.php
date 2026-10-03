<?php

namespace App\Http\Middleware;

use App\Models\AdminAllowedIp;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * İzin listesi boşsa herkes geçer. Liste .env (ADMIN_ALLOWED_IPS) ile
 * admin panelindeki kayıtların birleşimidir; eşleşmeyen istek 404 görür,
 * böylece admin yolunun varlığı da belli olmaz.
 */
class RestrictAdminIp
{
    public const CACHE_KEY = 'admin_allowed_ips';

    public function handle(Request $request, Closure $next): Response
    {
        $allowed = $this->allowedRanges();

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function allowedRanges(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, fn (): array => AdminAllowedIp::query()->pluck('cidr')->all());

        return array_values(array_unique([...config('hova.admin.allowed_ips', []), ...$stored]));
    }
}
