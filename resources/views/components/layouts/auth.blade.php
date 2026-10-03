@props([
    'title' => null,
])

<x-layouts.base :title="$title" :noindex="true" theme="dark">
    <div class="hm-auth">
        <aside class="hm-auth__aside">
            <x-ui.logo :href="route('home')" />
            <p class="hm-auth__statement m-0">{{ __('auth.aside_statement') }}</p>
            <p class="hm-code hm-muted m-0">hovamusic.com</p>
        </aside>
        <main class="hm-auth__main" id="icerik">
            <div class="lg:hidden">
                <x-ui.logo :href="route('home')" />
            </div>
            <div class="hm-auth__form">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-layouts.base>
