<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Seo\SeoFactory;
use App\Support\Settings;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function how(SeoFactory $seo, Settings $settings): View
    {
        $meta = $seo->page('how')->breadcrumb(__('site.nav.how'), route('how'));

        return view('site.how', ['seo' => $meta, 'leadDays' => $settings->releaseLeadDays()]);
    }

    public function about(SeoFactory $seo): View
    {
        $meta = $seo->page('about')->breadcrumb(__('site.nav.about'), route('about'));

        return view('site.about', ['seo' => $meta]);
    }
}
