<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Support\Content\PublicContent;
use App\Support\Seo\Seo;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request, SeoFactory $seo, PublicContent $content): View
    {
        $meta = $seo->page('blog')->breadcrumb(__('site.nav.blog'), route('blog.index'));
        $this->paginationNoindex($request, $meta);

        return view('site.blog.index', [
            'seo' => $meta,
            'posts' => $this->query()->paginate(self::PER_PAGE),
            'categories' => $content->categories(),
            'current' => null,
        ]);
    }

    public function category(Request $request, PostCategory $category, SeoFactory $seo, PublicContent $content): View
    {
        $meta = $seo->page('blog_category', ['name' => $category->name, 'description' => (string) $category->description])
            ->breadcrumb(__('site.nav.blog'), route('blog.index'))
            ->breadcrumb($category->name, route('blog.category', $category));
        $this->paginationNoindex($request, $meta);

        return view('site.blog.index', [
            'seo' => $meta,
            'posts' => $this->query()->where('post_category_id', $category->id)->paginate(self::PER_PAGE),
            'categories' => $content->categories(),
            'current' => $category,
        ]);
    }

    public function show(string $slug, SeoFactory $seo): View
    {
        $post = Post::query()->where('locale', 'tr')->where('slug', $slug)->with('category')->firstOrFail();
        abort_unless($post->isPublished() || auth('admin')->check(), 404);

        $meta = $seo->make($post->seo_title ?: $post->title, $post->seo_description ?: $post->excerpt);
        $meta->ogType = 'article';
        $meta->noindex = $post->noindex || ! $post->isPublished() || $meta->noindex;
        $cover = $post->coverSources();
        $image = $cover['webp'][1280] ?? $cover['fallback'];
        $meta->ogImage = $image ?? $meta->ogImage;
        $meta->breadcrumb(__('site.nav.blog'), route('blog.index'));

        if ($post->category) {
            $meta->breadcrumb($post->category->name, route('blog.category', $post->category));
        }

        $meta->breadcrumb($post->title, $post->url());
        $meta->schema(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $image ? [$image] : null,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'author' => ['@type' => $post->author_name ? 'Person' : 'Organization', 'name' => $post->author_name ?: __('seo.site_name')],
            'publisher' => ['@type' => 'Organization', 'name' => __('seo.site_name'), 'logo' => ['@type' => 'ImageObject', 'url' => asset('images/hova-music-logo.png')]],
            'mainEntityOfPage' => $post->url(),
            'inLanguage' => 'tr',
        ]));

        $related = $this->query()
            ->whereKeyNot($post->id)
            ->when($post->post_category_id, fn ($query) => $query->where('post_category_id', $post->post_category_id))
            ->limit(3)
            ->get();

        return view('site.blog.show', ['seo' => $meta, 'post' => $post, 'cover' => $cover, 'related' => $related]);
    }

    /**
     * @return Builder<Post>
     */
    private function query(): Builder
    {
        return Post::query()->published()->where('locale', 'tr')->with('category:id,name,slug')->latest('published_at');
    }

    /**
     * Sayfalamanın ikinci ve sonraki sayfaları dizine eklenmez; canonical ilk sayfayı gösterir.
     */
    private function paginationNoindex(Request $request, Seo $meta): void
    {
        if ((int) $request->query('page', 1) > 1) {
            $meta->noindex = true;
        }
    }
}
