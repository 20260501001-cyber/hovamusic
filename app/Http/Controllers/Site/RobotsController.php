<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * Otomatik robots.txt. Üretim dışındaki ortamlar tamamen kapalıdır. Admin yolu
 * gizli kalsın diye burada yazılmaz; admin sayfaları X-Robots-Tag ile korunur.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = app()->isProduction()
            ? ['User-agent: *', 'Disallow: /panel', 'Disallow: /medya/', 'Disallow: /webhooks/', 'Disallow: /goruntuleme/', 'Allow: /', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
