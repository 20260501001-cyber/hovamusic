<?php

namespace App\Http\Middleware;

use App\Models\Redirect;
use App\Models\SeoMeta;
use App\Support\Content\ContentCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panelinden yönetilen 301/302 yönlendirmeleri. Yalnızca GET ve HEAD
 * isteklerinde, yönlendirme tablosu önbellekten okunarak uygulanır.
 */
class HandleRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $redirects = ContentCache::remember('redirects', fn (): array => Redirect::query()
            ->where('is_active', true)
            ->get(['id', 'from_path', 'to_path', 'code'])
            ->mapWithKeys(fn (Redirect $redirect): array => [$redirect->from_path => [$redirect->id, $redirect->to_path, $redirect->code]])
            ->all());

        $path = SeoMeta::normalizePath('/'.$request->path());

        if (! isset($redirects[$path])) {
            return $next($request);
        }

        [$id, $to, $code] = $redirects[$path];
        Redirect::query()->whereKey($id)->incrementEach(['hits' => 1], ['last_hit_at' => now()]);

        $target = preg_match('#^https?://#i', $to) ? $to : url($to);

        if ($request->getQueryString() !== null) {
            $target .= (str_contains($target, '?') ? '&' : '?').$request->getQueryString();
        }

        return redirect()->to($target, in_array($code, [301, 302, 307, 308], true) ? $code : 301);
    }
}
