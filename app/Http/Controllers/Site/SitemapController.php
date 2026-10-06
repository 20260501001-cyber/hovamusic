<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use App\Models\Post;
use App\Models\SeoMeta;
use App\Support\Content\ContentCache;
use App\Support\Content\PublicContent;
use Illuminate\Http\Response;

/**
 * Otomatik sitemap.xml: sabit sayfalar, blog kategorileri, yayındaki yazılar ve
 * yayımlanmış yasal metinler. Admin'in noindex işaretlediği yollar çıkarılır.
 */
class SitemapController extends Controller
{
    public function __invoke(PublicContent $content): Response
    {
        $xml = ContentCache::remember('sitemap', function () use ($content): string {
            $noindex = SeoMeta::query()->where('noindex', true)->pluck('path')->flip();
            $urls = [];

            foreach (['home' => '1.0', 'pricing' => '0.9', 'how' => '0.8', 'platforms' => '0.7', 'faq' => '0.7', 'blog.index' => '0.7', 'about' => '0.5', 'contact' => '0.5'] as $route => $priority) {
                $urls[] = ['loc' => route($route), 'priority' => $priority, 'lastmod' => null];
            }

            foreach ($content->categories() as $category) {
                $urls[] = ['loc' => route('blog.category', $category), 'priority' => '0.5', 'lastmod' => null];
            }

            Post::query()->published()->where('locale', 'tr')->where('noindex', false)->orderByDesc('published_at')
                ->get(['slug', 'updated_at', 'published_at'])
                ->each(function (Post $post) use (&$urls): void {
                    $urls[] = ['loc' => route('blog.show', $post->slug), 'priority' => '0.6', 'lastmod' => $post->updated_at];
                });

            LegalDocument::query()->where('is_public', true)->with('currentVersion')->orderBy('sort')->get()
                ->filter(fn (LegalDocument $doc): bool => $doc->currentVersion !== null)
                ->each(function (LegalDocument $doc) use (&$urls): void {
                    $urls[] = ['loc' => route('legal.show', $doc->slug), 'priority' => '0.3', 'lastmod' => $doc->currentVersion->published_at];
                });

            $urls = array_filter($urls, fn (array $url): bool => ! $noindex->has(SeoMeta::normalizePath((string) parse_url($url['loc'], PHP_URL_PATH))));

            return view('site.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
