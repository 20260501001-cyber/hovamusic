@php($siteKey = config('services.turnstile.site_key'))

@if ($siteKey)
    <div class="hm-field">
        <div class="cf-turnstile" data-sitekey="{{ $siteKey }}" data-theme="{{ $attributes->get('theme', 'dark') }}" data-language="tr"></div>
        @error('turnstile')
            <p class="hm-field__error"><x-lucide-circle-x class="hm-icon" width="16" height="16" aria-hidden="true" /><span>{{ $message }}</span></p>
        @enderror
    </div>
    @once
        @push('scripts')
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
        @endpush
    @endonce
@else
    @error('turnstile')
        <x-ui.alert tone="danger" :title="$message" />
    @enderror
@endif
