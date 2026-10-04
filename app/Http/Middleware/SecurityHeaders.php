<?php

namespace App\Http\Middleware;

use App\Providers\Filament\AdminPanelProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), usb=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $headers['Content-Security-Policy'] = $this->contentSecurityPolicy($request, (string) Vite::cspNonce());
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * Herkese açık site katı politikayla çalışır. Kullanıcı ve admin paneli
     * Alpine/Livewire ifadelerini çalıştırabilmek için 'unsafe-eval' alır.
     */
    private function contentSecurityPolicy(Request $request, string $nonce): string
    {
        $script = ["'self'", "'nonce-{$nonce}'", 'https://challenges.cloudflare.com'];
        $style = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];

        $image = ["'self'", 'data:', 'blob:'];

        if ($this->isApplicationArea($request)) {
            $script[] = "'unsafe-eval'";
            // Spotify sanatçı aramasındaki profil görselleri.
            $image[] = 'https://i.scdn.co';
        }

        if (Vite::isRunningHot()) {
            $devServer = rtrim((string) file_get_contents(public_path('hot')));
            $script[] = $devServer;
            $style[] = $devServer;
            $connect[] = $devServer;
            $connect[] = preg_replace('/^http/', 'ws', $devServer);
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $script,
            'style-src' => $style,
            'img-src' => $image,
            'font-src' => ["'self'", 'data:'],
            'connect-src' => $connect,
            'media-src' => ["'self'", 'blob:'],
            'frame-src' => ['https://challenges.cloudflare.com'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        if ($request->isSecure()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => trim($directive.' '.implode(' ', $sources)))
            ->implode('; ');
    }

    private function isApplicationArea(Request $request): bool
    {
        $adminPath = AdminPanelProvider::path();

        return $request->is('panel', 'panel/*', 'livewire/*')
            || ($adminPath !== '' && $request->is($adminPath, $adminPath.'/*'));
    }
}
