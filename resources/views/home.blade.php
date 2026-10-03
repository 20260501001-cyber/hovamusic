<x-layouts.public :description="__('site.home.description')">
    <section class="hm-container py-16 md:py-24 grid gap-6">
        <p class="hm-eyebrow">{{ __('site.home.eyebrow') }}</p>
        <h1 class="hm-display-xl m-0">Hova Music</h1>
        <p class="hm-lead m-0">{{ __('site.home.lead') }}</p>
        <div class="flex flex-wrap gap-3">
            <x-ui.button :href="route('register')" variant="primary" icon-right="arrow-right">{{ __('site.nav.register') }}</x-ui.button>
            <x-ui.button :href="route('login')" variant="secondary">{{ __('site.nav.login') }}</x-ui.button>
        </div>
    </section>
</x-layouts.public>
