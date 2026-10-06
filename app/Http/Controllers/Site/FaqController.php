<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Support\Content\PublicContent;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;

class FaqController extends Controller
{
    public function __invoke(SeoFactory $seo, PublicContent $content): View
    {
        $faqs = $content->faqs();
        $meta = $seo->page('faq')->breadcrumb(__('site.nav.faq'), route('faq'));

        if ($faqs->isNotEmpty()) {
            $meta->schema([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn (Faq $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq->question,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq->plainAnswer()],
                ])->values()->all(),
            ]);
        }

        return view('site.faq', [
            'seo' => $meta,
            'groups' => $faqs->groupBy(fn (Faq $faq): string => $faq->group ?: __('site.faq.general')),
        ]);
    }
}
