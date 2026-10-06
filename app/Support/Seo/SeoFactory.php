<?php

namespace App\Support\Seo;

use App\Models\SeoMeta;
use App\Support\Content\ContentCache;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

/**
 * Sayfanın varsayılan SEO bilgisini (lang/tr/seo.php) kurar ve admin panelinden o
 * yol için girilmiş değerlerle (seo_meta) değiştirir.
 */
class SeoFactory
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  string  $page  lang/tr/seo.php içindeki sayfa anahtarı
     * @param  array<string, string>  $replace
     */
    public function page(string $page, array $replace = []): Seo
    {
        return $this->make(
            (string) __("seo.pages.{$page}.title", $replace),
            (string) __("seo.pages.{$page}.description", $replace),
        );
    }

    public function make(string $title, ?string $description = null): Seo
    {
        $seo = new Seo($title, $description, $this->canonical());
        $seo->alternates = $this->alternates();
        $seo->ogImage = $this->defaultImage();
        $seo->breadcrumb((string) __('seo.breadcrumb_home'), route('home'));

        return $this->applyOverrides($seo);
    }

    /**
     * Admin'in bu yol için girdiği değerler varsayılanların yerine geçer.
     */
    public function applyOverrides(Seo $seo): Seo
    {
        $path = SeoMeta::normalizePath(request()->path());
        $meta = ContentCache::remember('seo:'.$path, fn (): ?array => SeoMeta::query()->where('path', $path)->first()?->toArray());

        if ($meta === null) {
            return $seo;
        }

        $seo->title = filled($meta['title']) ? $meta['title'] : $seo->title;
        $seo->titleIsFull = filled($meta['title']) ? true : $seo->titleIsFull;
        $seo->description = filled($meta['description']) ? $meta['description'] : $seo->description;
        $seo->canonical = filled($meta['canonical']) ? $meta['canonical'] : $seo->canonical;
        $seo->ogTitle = filled($meta['og_title']) ? $meta['og_title'] : $seo->ogTitle;
        $seo->ogDescription = filled($meta['og_description']) ? $meta['og_description'] : $seo->ogDescription;
        $seo->ogImage = filled($meta['og_image_path']) ? Storage::disk('public')->url($meta['og_image_path']) : $seo->ogImage;
        $seo->twitterCard = $meta['twitter_card'] ?: $seo->twitterCard;
        $seo->noindex = (bool) $meta['noindex'] || $seo->noindex;

        return $seo;
    }

    /**
     * Site geneli yapılandırılmış veri: kuruluş ve web sitesi.
     *
     * @return list<array<string, mixed>>
     */
    public function siteSchemas(): array
    {
        $home = route('home');

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $home.'#organization',
                'name' => (string) __('seo.site_name'),
                'url' => $home,
                'logo' => asset('images/hova-music-logo.png'),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => (string) __('seo.site_name'),
                'url' => $home,
                'inLanguage' => app()->getLocale(),
                'publisher' => ['@id' => $home.'#organization'],
            ],
        ];
    }

    public function verificationCode(): ?string
    {
        $code = trim((string) $this->settings->get('google_site_verification'));

        return $code !== '' ? $code : null;
    }

    private function canonical(): string
    {
        return url()->current();
    }

    private function defaultImage(): ?string
    {
        return file_exists(public_path('images/og-default.png')) ? asset('images/og-default.png') : null;
    }

    /**
     * Etkin dillerin karşılıkları. Şimdilik yalnızca Türkçe; İngilizce açılınca
     * aynı yolun /en altındaki karşılığı eklenir.
     *
     * @return array<string, string>
     */
    private function alternates(): array
    {
        $path = '/'.ltrim(request()->path(), '/');
        $alternates = [];

        foreach (config('hova.locales', []) as $locale => $options) {
            if (! ($options['enabled'] ?? false)) {
                continue;
            }

            $prefix = trim((string) ($options['prefix'] ?? ''), '/');
            $alternates[$options['hreflang'] ?? $locale] = url(($prefix !== '' ? '/'.$prefix : '').($path === '/' ? '' : $path)) ?: url('/');
        }

        if ($alternates !== []) {
            $alternates['x-default'] = reset($alternates);
        }

        return $alternates;
    }
}
