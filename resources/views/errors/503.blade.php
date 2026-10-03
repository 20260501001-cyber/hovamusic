<x-layouts.public :title="__('errors.503.title')">
    <section class="hm-container hm-error">
        <p class="hm-error__code">HATA 503</p>
        <h1 class="hm-display-l m-0">{{ __('errors.503.title') }}</h1>
        <p class="hm-lead m-0">{{ __('errors.503.body') }}</p>
        <div><x-ui.button :href="url('/')" variant="secondary" icon="arrow-left">{{ __('errors.home') }}</x-ui.button></div>
    </section>
</x-layouts.public>
