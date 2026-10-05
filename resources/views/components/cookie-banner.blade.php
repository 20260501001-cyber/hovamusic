{{-- Çerez bandı: tercih yapılmadıysa görünür; "Çerez tercihleri" bağlantısı bandı yeniden açar. --}}
@php
    $current = \App\Support\CookieConsent::current();
    $legal = route('legal.show', 'cerez-politikasi');
@endphp

<section id="cerez-bandi" class="hm-cookie" aria-labelledby="cerez-baslik" @if ($current) hidden @endif data-cookie-banner>
    <form method="POST" action="{{ route('cookies.store') }}" class="hm-cookie__inner">
        @csrf
        <div class="grid gap-1">
            <h2 id="cerez-baslik" class="m-0 text-base font-semibold">{{ __('privacy.cookies.title') }}</h2>
            <p class="m-0 text-sm text-ink-muted">
                {{ __('privacy.cookies.body') }} <a class="hm-link" href="{{ $legal }}">{{ __('privacy.cookies.policy') }}</a>
            </p>
        </div>

        <details class="hm-cookie__details" @if ($current) open @endif>
            <summary class="hm-link cursor-pointer text-sm">{{ __('privacy.cookies.customize') }}</summary>
            <div class="mt-3 grid gap-2">
                <x-ui.checkbox name="essential" id="cerez-zorunlu" :checked="true" disabled :description="__('privacy.cookies.categories.essential_help')">
                    {{ __('privacy.cookies.categories.essential') }}
                </x-ui.checkbox>
                @foreach (\App\Support\CookieConsent::CATEGORIES as $category)
                    <x-ui.checkbox :name="$category" :id="'cerez-'.$category" :checked="(bool) ($current[$category] ?? false)" :description="__('privacy.cookies.categories.'.$category.'_help')">
                        {{ __('privacy.cookies.categories.'.$category) }}
                    </x-ui.checkbox>
                @endforeach
                <div><x-ui.button type="submit" name="choice" value="custom" size="s">{{ __('privacy.cookies.save') }}</x-ui.button></div>
            </div>
        </details>

        <div class="flex flex-wrap gap-2">
            <x-ui.button type="submit" name="choice" value="essential" size="s">{{ __('privacy.cookies.essential_only') }}</x-ui.button>
            <x-ui.button type="submit" name="choice" value="all" size="s" variant="primary">{{ __('privacy.cookies.accept_all') }}</x-ui.button>
        </div>
    </form>
</section>
