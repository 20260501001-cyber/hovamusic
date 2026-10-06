@props(['seo'])

{{-- Başlık, açıklama, canonical, hreflang, Open Graph, Twitter kartı ve JSON-LD. --}}
<title>{{ $seo->fullTitle() }}</title>
@if (filled($seo->description))<meta name="description" content="{{ $seo->description }}">@endif
@if ($seo->noindex)
    <meta name="robots" content="noindex, follow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large">
@endif
@if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
@foreach ($seo->alternates as $hreflang => $href)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach
<meta property="og:site_name" content="{{ __('seo.site_name') }}">
<meta property="og:locale" content="tr_TR">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:title" content="{{ $seo->ogTitle ?? $seo->fullTitle() }}">
@if (filled($seo->ogDescription ?? $seo->description))<meta property="og:description" content="{{ $seo->ogDescription ?? $seo->description }}">@endif
@if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
@if ($seo->ogImage)<meta property="og:image" content="{{ $seo->ogImage }}">@endif
<meta name="twitter:card" content="{{ $seo->ogImage ? $seo->twitterCard : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->ogTitle ?? $seo->fullTitle() }}">
@if (filled($seo->ogDescription ?? $seo->description))<meta name="twitter:description" content="{{ $seo->ogDescription ?? $seo->description }}">@endif
@if ($seo->ogImage)<meta name="twitter:image" content="{{ $seo->ogImage }}">@endif
@if ($verification = app(\App\Support\Seo\SeoFactory::class)->verificationCode())
    <meta name="google-site-verification" content="{{ $verification }}">
@endif
@foreach ($seo->allSchemas() as $schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
