<?php

namespace App\Http\Controllers;

use App\Support\Content\PublicContent;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(SeoFactory $seo, PublicContent $content): View
    {
        $meta = $seo->page('home');
        $meta->titleIsFull = true;
        $meta->breadcrumbs = [];

        foreach ($seo->siteSchemas() as $schema) {
            $meta->schema($schema);
        }

        return view('home', [
            'seo' => $meta,
            'plans' => $content->plans(),
            'platforms' => $content->platformNames(),
            'faqs' => $content->faqs(homeOnly: true),
            'posts' => $content->latestPosts(),
        ]);
    }
}
