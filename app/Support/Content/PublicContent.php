<?php

namespace App\Support\Content;

use App\Enums\AccountType;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Platform;
use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Support\Collection;

/**
 * Herkese açık sayfaların önbellekli verileri.
 */
class PublicContent
{
    /**
     * Satıştaki planlar, hesap türüne göre.
     *
     * @return array<string, Collection<int, Plan>>
     */
    public function plans(): array
    {
        $plans = ContentCache::remember('plans', fn () => Plan::query()
            ->where('is_active', true)
            ->whereNotNull('polar_product_id')
            ->orderBy('sort')
            ->orderBy('price_usd')
            ->get());

        return [
            AccountType::Artist->value => $plans->where('audience', AccountType::Artist)->values(),
            AccountType::Label->value => $plans->where('audience', AccountType::Label)->values(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    public function platformNames(): Collection
    {
        return ContentCache::remember('platforms', fn () => Platform::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->pluck('name'));
    }

    /**
     * @return Collection<int, Faq>
     */
    public function faqs(bool $homeOnly = false): Collection
    {
        return ContentCache::remember('faqs:'.($homeOnly ? 'home' : 'all'), fn () => Faq::query()
            ->where('locale', 'tr')
            ->where('is_published', true)
            ->when($homeOnly, fn ($query) => $query->where('show_on_home', true))
            ->orderBy('sort')
            ->orderBy('id')
            ->get());
    }

    /**
     * Yazısı olan kategoriler.
     *
     * @return Collection<int, PostCategory>
     */
    public function categories(): Collection
    {
        return ContentCache::remember('post-categories', fn () => PostCategory::query()
            ->whereHas('posts', fn ($query) => $query->published()->where('locale', 'tr'))
            ->orderBy('sort')
            ->orderBy('name')
            ->get());
    }

    /**
     * @return Collection<int, Post>
     */
    public function latestPosts(int $limit = 3): Collection
    {
        return ContentCache::remember('posts:latest:'.$limit, fn () => Post::query()
            ->published()
            ->where('locale', 'tr')
            ->with('category:id,name,slug')
            ->latest('published_at')
            ->limit($limit)
            ->get());
    }
}
