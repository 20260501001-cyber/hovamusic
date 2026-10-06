{{-- Yalnızca local: paylaşım görseli (1200×630) kaynağı; hova:screenshots bunu PNG olarak kaydeder. --}}
<!DOCTYPE html>
<html lang="tr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css'])
    <style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        html, body { margin: 0; width: 1200px; height: 630px; overflow: hidden; background: var(--ground); }
        .og { box-sizing: border-box; width: 1200px; height: 630px; padding: 72px 80px; display: grid; grid-template-rows: auto 1fr auto; }
        .og .hm-brand__mark { width: 64px; height: 64px; }
        .og .hm-brand__word { font-size: 30px; }
        .og h1 { align-self: end; margin: 0; font-size: 76px; line-height: 78px; font-weight: 800; font-stretch: 125%; letter-spacing: -0.02em; color: var(--ink); max-width: 15ch; }
        .og footer { display: flex; justify-content: space-between; align-items: center; margin-top: 40px; padding-top: 24px; border-top: 2px solid var(--accent); }
    </style>
</head>
<body>
    <div class="og">
        <x-ui.logo />
        <h1>{{ __('site.home.title') }}</h1>
        <footer>
            <span class="hm-eyebrow">{{ __('site.home.eyebrow') }}</span>
            <span class="hm-code hm-muted">hovamusic.com</span>
        </footer>
    </div>
</body>
</html>
