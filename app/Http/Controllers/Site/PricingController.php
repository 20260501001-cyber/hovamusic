<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Content\PublicContent;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;

class PricingController extends Controller
{
    public function __invoke(SeoFactory $seo, PublicContent $content): View
    {
        $plans = $content->plans();
        $meta = $seo->page('pricing')->breadcrumb(__('site.nav.pricing'), route('pricing'));

        foreach (collect($plans)->flatten() as $plan) {
            /** @var Plan $plan */
            $meta->schema([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => 'Hova Music '.$plan->name,
                'description' => $plan->description ?: __('seo.pages.pricing.description'),
                'brand' => ['@type' => 'Brand', 'name' => __('seo.site_name')],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => (string) $plan->price_usd,
                    'priceCurrency' => 'USD',
                    'availability' => 'https://schema.org/InStock',
                    'url' => route('pricing'),
                ],
            ]);
        }

        return view('site.pricing', ['seo' => $meta, 'plans' => $plans]);
    }
}
