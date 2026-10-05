{{-- Hesap ayarları: KVKK veri talepleri, hesap silme ve çerez tercihleri. --}}
@php
    $bag = $errors->getBag('privacy');
    $oldType = old('type');
@endphp

<section class="hm-card" id="veriler" aria-labelledby="veriler-baslik">
    <div class="hm-card__head">
        <h2 id="veriler-baslik" class="hm-h3">{{ __('privacy.section.title') }}</h2>
        <p class="hm-muted m-0 text-sm">{{ __('privacy.section.help') }}</p>
    </div>
    <div class="hm-card__body">
        <ul class="m-0 flex list-none flex-wrap gap-x-4 gap-y-1 p-0 text-sm">
            <li><a class="hm-link" href="{{ route('legal.show', 'kvkk-aydinlatma-metni') }}">{{ __('privacy.section.notice') }}</a></li>
            <li><a class="hm-link" href="{{ route('legal.show', 'gizlilik-politikasi') }}">{{ __('privacy.section.policy') }}</a></li>
            <li><button type="button" class="hm-link cursor-pointer border-0 bg-transparent p-0" data-cookie-settings>{{ __('privacy.section.cookies') }}</button></li>
        </ul>

        @if ($dataRequests->isNotEmpty())
            <ol class="m-0 grid list-none gap-3 p-0">
                @foreach ($dataRequests as $item)
                    @php($at = $item->created_at->timezone(config('hova.display_timezone')))
                    <li class="grid gap-1 border-l-2 border-line pl-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="font-semibold">{{ $item->type->label() }}</strong>
                            <span @class(['hm-badge', 'hm-badge--warning' => $item->isPending(), 'hm-badge--success' => $item->status->value === 'completed', 'hm-badge--danger' => $item->status->value === 'rejected'])>{{ $item->status->label() }}</span>
                            <time class="text-sm text-ink-muted" datetime="{{ $item->created_at->toIso8601String() }}">{{ \App\Support\Format::longDate($at) }}</time>
                        </div>
                        @if ($item->admin_note)
                            <p class="m-0 text-sm">{{ $item->admin_note }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('panel.privacy.store') }}" class="grid content-start gap-3 rounded-sm border border-line p-4">
                @csrf
                <input type="hidden" name="type" value="export">
                <h3 class="m-0 text-base font-semibold">{{ __('privacy.export.title') }}</h3>
                <p class="m-0 text-sm text-ink-muted">{{ __('privacy.export.help') }}</p>
                <div><x-ui.button type="submit" size="s" icon="download">{{ __('privacy.export.submit') }}</x-ui.button></div>
            </form>

            <form method="POST" action="{{ route('panel.privacy.store') }}" class="grid content-start gap-3 rounded-sm border border-line p-4">
                @csrf
                <input type="hidden" name="type" value="correction">
                <h3 class="m-0 text-base font-semibold">{{ __('privacy.correction.title') }}</h3>
                <x-ui.field name="message" id="duzeltme-mesaj" bag="privacy" :label="__('privacy.fields.message')" :multiline="true" :rows="3" maxlength="2000"
                    :value="$oldType === 'correction' ? old('message') : null" :help="__('privacy.correction.help')" />
                <div><x-ui.button type="submit" size="s" icon="send">{{ __('privacy.correction.submit') }}</x-ui.button></div>
            </form>
        </div>

        <details class="rounded-sm border border-line p-4" @if ($oldType === 'deletion' && $bag->isNotEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold text-danger">{{ __('privacy.deletion.title') }}</summary>
            <form method="POST" action="{{ route('panel.privacy.store') }}" class="mt-4 grid gap-3" data-confirm="{{ __('privacy.deletion.confirm') }}">
                @csrf
                <input type="hidden" name="type" value="deletion">
                <p class="m-0 text-sm">{{ __('privacy.deletion.help') }}</p>
                <x-ui.field name="current_password" id="silme-sifre" type="password" bag="privacy" :label="__('auth.fields.current_password')" autocomplete="current-password" required />
                <x-ui.field name="message" id="silme-mesaj" bag="privacy" :label="__('privacy.deletion.reason')" :optional="true" :multiline="true" :rows="2" maxlength="2000"
                    :value="$oldType === 'deletion' ? old('message') : null" />
                <div><x-ui.button type="submit" size="s" variant="danger" icon="trash-2">{{ __('privacy.deletion.submit') }}</x-ui.button></div>
            </form>
        </details>

        <x-ui.field-error id="veri-talebi-hata" :message="$bag->first('type')" />
    </div>
</section>
