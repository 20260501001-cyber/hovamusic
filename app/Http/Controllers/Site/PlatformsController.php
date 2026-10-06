<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Support\Content\PublicContent;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;

class PlatformsController extends Controller
{
    public function __invoke(SeoFactory $seo, PublicContent $content): View
    {
        return view('site.platforms', [
            'seo' => $seo->page('platforms')->breadcrumb(__('site.nav.platforms'), route('platforms')),
            'platforms' => $content->platformNames(),
        ]);
    }
}
