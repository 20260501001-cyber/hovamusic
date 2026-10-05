@use('App\Support\Format')

<x-layouts.panel :title="__('plans.confirm.title')">
    <div class="hm-page-head">
        <a class="hm-link text-sm" href="{{ route('panel.plans.index') }}">← {{ __('plans.confirm.back') }}</a>
        <h1 class="hm-h1">{{ __('plans.confirm.title') }}</h1>
    </div>

    <section class="hm-card" aria-labelledby="ozet-baslik">
        <div class="hm-card__head">
            <p class="hm-eyebrow">{{ __('plans.confirm.summary') }}</p>
            <h2 id="ozet-baslik" class="hm-h2">{{ $plan->name }}</h2>
        </div>
        <div class="hm-card__body">
            <dl class="m-0 grid gap-4 sm:grid-cols-2">
                <div class="hm-kv">
                    <dt>{{ __('plans.confirm.price') }}</dt>
                    <dd>{{ Format::money((string) $plan->price_usd) }} {{ __('plans.per.'.$plan->interval->value) }}</dd>
                </div>
                <div class="hm-kv">
                    <dt>{{ __('plans.index.revenue_share') }}</dt>
                    <dd>%{{ Format::decimal((float) $plan->revenue_share_pct, 2) }}</dd>
                </div>
            </dl>
            <p class="m-0 text-sm text-ink-muted">{{ __('plans.confirm.renewal') }} {{ __('plans.confirm.tax_note') }}</p>
        </div>
    </section>

    <form method="POST" action="{{ route('panel.plans.checkout', $plan) }}" class="grid gap-6" novalidate>
        @csrf
        @foreach ([['on-bilgilendirme', $preInfo, 'pre_info'], ['mesafeli-satis', $contract, 'contract']] as [$type, $document, $key])
            <section class="hm-card" aria-labelledby="metin-{{ $type }}">
                <div class="hm-card__head">
                    <h2 id="metin-{{ $type }}" class="hm-h3">{{ __('plans.confirm.'.$key) }}</h2>
                </div>
                <div class="hm-card__body">
                    @if ($document?->currentVersion)
                        <div class="hm-legal-box hm-prose" tabindex="0" role="region" aria-label="{{ __('plans.confirm.'.$key) }}">
                            @include('panel.plans.partials.order-facts', ['plan' => $plan])
                            {!! $document->currentVersion->html() !!}
                        </div>
                        <a class="hm-link text-sm" href="{{ route('legal.show', $document->slug) }}" target="_blank" rel="noopener">{{ __('plans.confirm.open_full') }}</a>
                    @else
                        <x-ui.alert tone="warning" :title="__('plans.confirm.document_missing')" />
                    @endif
                    <x-ui.checkbox :name="'consents['.$type.']'" :id="'onay-'.$type">{{ __('plans.confirm.'.$key.'_accept') }}</x-ui.checkbox>
                </div>
            </section>
        @endforeach

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
            <p class="m-0 text-sm text-ink-muted">{{ __('plans.confirm.provider_note') }}</p>
            <x-ui.button type="submit" variant="primary" icon-right="arrow-right">{{ __('plans.confirm.submit') }}</x-ui.button>
        </div>
    </form>
</x-layouts.panel>
