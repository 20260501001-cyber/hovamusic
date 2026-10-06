<?php

namespace App\Support\Seo;

/**
 * Bir sayfanın arama motoru ve paylaşım bilgileri: başlık, açıklama, canonical,
 * Open Graph, Twitter kartı, hreflang ve yapılandırılmış veri (JSON-LD).
 */
final class Seo
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $schemas = [];

    /**
     * @var list<array{name: string, url: string}>
     */
    public array $breadcrumbs = [];

    /**
     * @var array<string, string> hreflang => adres
     */
    public array $alternates = [];

    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public string $twitterCard = 'summary_large_image',
        public bool $noindex = false,
        public bool $titleIsFull = false,
    ) {}

    public function fullTitle(): string
    {
        $site = (string) __('seo.site_name');

        return $this->titleIsFull || $this->title === $site ? $this->title : $this->title.' · '.$site;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function schema(array $schema): self
    {
        $this->schemas[] = $schema;

        return $this;
    }

    public function breadcrumb(string $name, string $url): self
    {
        $this->breadcrumbs[] = ['name' => $name, 'url' => $url];

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allSchemas(): array
    {
        $schemas = $this->schemas;

        if (count($this->breadcrumbs) > 1) {
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_map(fn (array $crumb, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'],
                ], $this->breadcrumbs, array_keys($this->breadcrumbs)),
            ];
        }

        return $schemas;
    }
}
