<?php

use App\Enums\PostStatus;
use App\Models\ContactMessage;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Redirect;
use App\Models\SeoMeta;
use Illuminate\Support\Facades\Notification;

it('renders every public page with its own title, description and canonical link', function (string $route) {
    $response = $this->get(route($route))->assertOk();
    $html = $response->getContent();

    expect($html)->toContain('<title>')
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical" href="'.route($route).'">')
        ->toContain('<meta property="og:title"')
        ->toContain('application/ld+json')
        ->and($response->headers->get('X-Robots-Tag'))->toBeNull();
})->with(['home', 'pricing', 'how', 'platforms', 'faq', 'blog.index', 'about', 'contact']);

it('serves a sitemap with the static pages and published posts only', function () {
    $category = PostCategory::query()->create(['name' => 'Rehberler', 'slug' => 'rehberler']);
    Post::query()->create(['post_category_id' => $category->id, 'locale' => 'tr', 'title' => 'Yayında', 'slug' => 'yayinda-yazi', 'excerpt' => 'Özet', 'body' => 'Metin', 'status' => PostStatus::Published, 'published_at' => now()->subDay()]);
    Post::query()->create(['locale' => 'tr', 'title' => 'Taslak', 'slug' => 'taslak-yazi', 'excerpt' => 'Özet', 'body' => 'Metin', 'status' => PostStatus::Draft]);
    Post::query()->create(['locale' => 'tr', 'title' => 'İleri tarihli', 'slug' => 'ileri-tarihli', 'excerpt' => 'Özet', 'body' => 'Metin', 'status' => PostStatus::Published, 'published_at' => now()->addWeek()]);
    SeoMeta::query()->create(['path' => '/hakkimizda', 'noindex' => true]);

    $xml = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect($xml)->toContain(route('pricing'))
        ->toContain(route('blog.category', $category))
        ->toContain(route('blog.show', 'yayinda-yazi'))
        ->not->toContain('taslak-yazi')
        ->not->toContain('ileri-tarihli')
        ->not->toContain(route('about'));
});

it('hides drafts and future posts from visitors', function () {
    $post = Post::query()->create(['locale' => 'tr', 'title' => 'Taslak', 'slug' => 'taslak-yazi', 'excerpt' => 'Özet', 'body' => "**kalın** metin\n\n<script>alert(1)</script>", 'status' => PostStatus::Draft]);

    $this->get(route('blog.show', $post->slug))->assertNotFound();

    $post->forceFill(['status' => PostStatus::Published, 'published_at' => now()->subMinute()])->save();

    $this->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('<strong>kalın</strong>', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('closes the whole site to crawlers outside production', function () {
    $this->get('/robots.txt')->assertOk()->assertSeeText('Disallow: /');

    app()->detectEnvironment(fn () => 'production');

    $robots = $this->get('/robots.txt')->getContent();

    expect($robots)->toContain('Disallow: /panel')
        ->toContain('Sitemap: '.route('sitemap'))
        ->not->toContain(config('hova.admin.path'));
});

it('applies redirects managed in the admin panel', function () {
    Redirect::query()->create(['from_path' => '/eski-fiyatlar', 'to_path' => '/fiyatlar', 'code' => 301, 'is_active' => true]);
    Redirect::query()->create(['from_path' => '/kapali', 'to_path' => '/sss', 'code' => 301, 'is_active' => false]);

    $this->get('/eski-fiyatlar?ref=x')->assertRedirect(url('/fiyatlar').'?ref=x')->assertStatus(301);
    $this->get('/kapali')->assertNotFound();

    expect(Redirect::query()->where('from_path', '/eski-fiyatlar')->value('hits'))->toBe(1);
});

it('renders a branded 404 page for unknown addresses', function () {
    $this->get('/boyle-bir-sayfa-yok')
        ->assertNotFound()
        ->assertSee(route('home'), false);
});

it('stores contact messages and validates the form', function () {
    Notification::fake();

    $this->post(route('contact.store'), ['name' => 'Deniz', 'email' => 'yanlis', 'topic' => 'gizli', 'message' => 'kısa'])
        ->assertSessionHasErrors(['email', 'topic', 'message'], errorBag: 'contact');

    $this->post(route('contact.store'), [
        'name' => 'Deniz Yılmaz',
        'email' => 'deniz@example.com',
        'topic' => 'payments',
        'message' => 'Ödeme takvimi hakkında bilgi almak istiyorum.',
    ])->assertRedirect(route('contact'))->assertSessionHas('flash');

    $message = ContactMessage::query()->sole();

    expect($message->topic)->toBe('payments')
        ->and($message->ip_address)->not->toBeNull();
});

it('uses admin SEO overrides for a page', function () {
    SeoMeta::query()->create(['path' => '/fiyatlar', 'title' => 'Özel fiyat başlığı', 'description' => 'Özel açıklama', 'noindex' => true]);

    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('Özel fiyat başlığı')
        ->assertSee('<meta name="description" content="Özel açıklama">', false)
        ->assertSee('noindex', false);
});
