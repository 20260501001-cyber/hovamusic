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
 * Herkese açık sayfaların verileri. Önbellek yalnızca düz diziler tutar (cache
 * nesne serileştirmeye kapalı); model sorguları küçük ve indeksli.
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
        $plans = Plan::query()
            ->where('is_active', true)
            ->whereNotNull('polar_product_id')
            ->orderBy('sort')
            ->orderBy('price_usd')
            ->get();

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
        return collect(ContentCache::remember('platforms', fn (): array => Platform::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->pluck('name')
            ->all()));
    }

    /**
     * @return Collection<int, Faq>
     */
    public function faqs(bool $homeOnly = false): Collection
    {
        return Faq::query()
            ->where('locale', 'tr')
            ->where('is_published', true)
            ->when($homeOnly, fn ($query) => $query->where('show_on_home', true))
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Yazısı olan kategoriler.
     *
     * @return Collection<int, PostCategory>
     */
    public function categories(): Collection
    {
        return PostCategory::query()
            ->whereHas('posts', fn ($query) => $query->published()->where('locale', 'tr'))
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function latestPosts(int $limit = 3): Collection
    {
        return Post::query()
            ->published()
            ->where('locale', 'tr')
            ->with('category:id,name,slug')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}
