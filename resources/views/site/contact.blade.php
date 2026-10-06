<x-layouts.public :seo="$seo">
    <section class="hm-container hm-page-hero" aria-labelledby="sayfa-baslik">
        @include('site.partials.breadcrumbs')
        <p class="hm-eyebrow">{{ __('site.contact.eyebrow') }}</p>
        <h1 id="sayfa-baslik" class="hm-display-l m-0">{{ __('site.contact.title') }}</h1>
        <p class="hm-lead m-0">{{ __('site.contact.lead') }}</p>
    </section>

    <section class="hm-container pb-16">
        <div class="hm-split">
            <dl class="hm-defs">
                @if ($email)
                    <div>
                        <dt>{{ __('site.contact.email_label') }}</dt>
                        <dd><a href="mailto:{{ $email }}" class="hm-link">{{ $email }}</a></dd>
                    </div>
                @endif
                <div>
                    <dt>{{ __('site.nav.faq') }}</dt>
                    <dd><a href="{{ route('faq') }}" class="hm-link">{{ __('site.faq.title') }}</a></dd>
                </div>
                <div>
                    <dd>{{ __('site.contact.response_time') }}</dd>
                </div>
            </dl>

            <div class="grid gap-5" style="max-width: 640px">
                <x-ui.flash />
                <form method="POST" action="{{ route('contact.store') }}" class="grid gap-4" novalidate>
                    @csrf
                    <div class="hm-form-grid hm-form-grid--2">
                        <x-ui.field name="name" :label="__('site.contact.name')" bag="contact" autocomplete="name" required />
                        <x-ui.field name="email" type="email" :label="__('site.contact.email')" bag="contact" autocomplete="email" required />
                    </div>
                    <x-ui.select name="topic" :label="__('site.contact.topic')" :options="$topics" bag="contact" required />
                    <x-ui.field name="message" :label="__('site.contact.message')" bag="contact" :multiline="true" :rows="7" required />
                    @if ($privacyUrl)
                        <p class="hm-field__help m-0">{!! __('site.contact.privacy', ['link' => '<a class="hm-link" href="'.e($privacyUrl).'">'.e(__('site.contact.privacy_link')).'</a>']) !!}</p>
                    @endif
                    <x-ui.turnstile />
                    @error('turnstile', 'contact')
                        <x-ui.alert tone="danger" :title="$message" />
                    @enderror
                    <div><x-ui.button type="submit" variant="primary" :loading-text="__('ui.loading')">{{ __('site.contact.submit') }}</x-ui.button></div>
                </form>
            </div>
        </div>
    </section>
</x-layouts.public>
