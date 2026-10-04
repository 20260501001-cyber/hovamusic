{{-- Onaylanan yayın için düzeltme ve kaldırma talepleri; $release->requests yüklü olmalı. --}}
@php
    $errorBag = $errors->getBag('request');
    $formOpen = $errorBag->isNotEmpty();
    $openTypes = $release->requests->filter->isOpen()->pluck('type');
    $defaultType = $canCorrection ? 'correction' : 'takedown';
@endphp

<section class="hm-card" aria-labelledby="talepler-baslik">
    <div class="hm-card__head">
        <h2 id="talepler-baslik" class="hm-h3">{{ __('release.requests.title') }}</h2>
        <p class="hm-muted m-0 text-sm">{{ __('release.requests.help') }}</p>
    </div>
    <div class="hm-card__body">
        @if ($release->requests->isEmpty())
            <p class="hm-muted m-0">{{ __('release.requests.empty') }}</p>
        @else
            <ol class="m-0 grid list-none gap-4 p-0">
                @foreach ($release->requests as $item)
                    @php($at = $item->created_at->timezone(config('hova.display_timezone')))
                    <li class="grid gap-2 border-l-2 border-line pl-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong class="font-semibold">{{ $item->type->label() }}</strong>
                            <span @class(['hm-badge', 'hm-badge--warning' => $item->isOpen(), 'hm-badge--success' => $item->status->value === 'resolved', 'hm-badge--danger' => $item->status->value === 'rejected'])>
                                <span class="hm-badge__dot" aria-hidden="true"></span>{{ $item->status->label() }}
                            </span>
                            <span class="text-sm text-ink-muted">
                                <time datetime="{{ $item->created_at->toIso8601String() }}">{{ \App\Support\Format::longDate($at) }} {{ $at->format('H:i') }}</time>
                            </span>
                        </div>
                        <p class="m-0 text-sm whitespace-pre-line">{{ $item->message }}</p>
                        @if ($item->admin_note)
                            <div class="grid gap-1 rounded-sm bg-surface-hover p-3">
                                <span class="text-sm font-semibold">{{ __('release.requests.answer_label') }}</span>
                                <p class="m-0 text-sm whitespace-pre-line">{{ $item->admin_note }}</p>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @foreach ($openTypes->unique() as $type)
            <p class="hm-field__help m-0">{{ __('release.requests.open_exists', ['type' => mb_strtolower($type->label())]) }}</p>
        @endforeach

        @if ($canCorrection || $canTakedown)
            <details class="border-t border-line pt-4" @if ($formOpen) open @endif>
                <summary class="hm-btn hm-btn--secondary hm-btn--m w-fit cursor-pointer list-none">
                    <x-lucide-message-square-plus class="hm-icon" width="18" height="18" aria-hidden="true" />
                    <span>{{ __('release.requests.new') }}</span>
                </summary>

                <form method="POST" action="{{ route('panel.releases.requests.store', $release) }}" class="mt-4 grid gap-4">
                    @csrf
                    <fieldset class="hm-field m-0 border-0 p-0">
                        <legend class="hm-label">{{ __('release.requests.fields.type') }}</legend>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @if ($canCorrection)
                                <x-ui.choice name="type" id="talep-duzeltme" value="correction" :checked="$defaultType === 'correction'"
                                    :title="__('release.requests.correction_title')" :description="__('release.requests.correction_help')" />
                            @endif
                            @if ($canTakedown)
                                <x-ui.choice name="type" id="talep-kaldirma" value="takedown" :checked="$defaultType === 'takedown'"
                                    :title="__('release.requests.takedown_title')" :description="__('release.requests.takedown_help')" />
                            @endif
                        </div>
                        <x-ui.field-error id="talep-tur-error" :message="$errorBag->first('type')" />
                    </fieldset>

                    <x-ui.field name="message" id="talep-mesaj" bag="request" :label="__('release.requests.fields.message')" :multiline="true" :rows="5"
                        maxlength="2000" required />

                    <div>
                        <x-ui.button type="submit" variant="primary" icon="send">{{ __('release.requests.submit') }}</x-ui.button>
                    </div>
                </form>
            </details>
        @endif
    </div>
</section>
