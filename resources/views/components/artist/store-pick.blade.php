@props([
    'store',
    'selected' => null,
    'results' => [],
    'message' => '',
    'byLink' => false,
    'linkModel',
    'linkId',
    'linkLive' => true,
    'selectAction',
    'selectArgs' => '',
    'clear',
    'toLink',
    'toSearch',
    'loadingTarget' => null,
])

{{--
    Mağaza profili seçici (Spotify ya da Apple Music). Ad yazıldıkça bulunan
    profiller listelenir; listede yoksa link alanı açılır. Seçim çağrısı:
    {selectAction}({selectArgs}, 'profil kimliği').
--}}
@php
    $label = __('artist.'.$store);
    $statusId = $linkId.'-status';
@endphp

<div {{ $attributes->class('hm-pick') }}>
    @if ($selected)
        <div class="hm-pick__item hm-pick__item--selected">
            @if (! empty($selected['image_url']))
                <img src="{{ $selected['image_url'] }}" alt="" width="40" height="40" class="hm-pick__avatar" referrerpolicy="no-referrer">
            @else
                <span class="hm-pick__avatar" aria-hidden="true"><x-lucide-circle-check class="hm-icon" width="20" height="20" /></span>
            @endif
            <span class="hm-pick__name">
                <span class="hm-sr">{{ __('artist.'.$store.'_selected') }}: </span>{{ $selected['name'] }}
                <span class="hm-pick__sub"><span class="hm-code">{{ $selected['id'] }}</span>@if (! empty($selected['genre'])) · {{ $selected['genre'] }}@endif</span>
            </span>
            <a href="{{ $selected['url'] }}" target="_blank" rel="noopener noreferrer" class="hm-btn hm-btn--ghost hm-btn--s hm-btn--icon">
                <x-lucide-external-link class="hm-icon" width="16" height="16" aria-hidden="true" />
                <span class="hm-sr">{{ __('artist.'.$store.'_open') }}</span>
            </a>
            <x-ui.button size="s" wire:click="{{ $clear }}">{{ __('artist.change') }}</x-ui.button>
        </div>
    @elseif ($byLink)
        <div class="hm-field">
            <label for="{{ $linkId }}" class="hm-label">{{ $store === 'spotify' ? __('artist.spotify_link') : __('artist.apple_id') }}</label>
            <input id="{{ $linkId }}" type="text" inputmode="url" class="hm-input" autocomplete="off" maxlength="200"
                placeholder="{{ $store === 'spotify' ? __('artist.spotify_link_help') : __('artist.apple_help') }}"
                @if ($linkLive) wire:model.live.debounce.500ms="{{ $linkModel }}" @else wire:model.live.blur="{{ $linkModel }}" @endif
                aria-describedby="{{ $statusId }}">
        </div>
        <p id="{{ $statusId }}" class="hm-pick__status" role="status">
            <x-lucide-loader-circle class="hm-icon hm-spin" width="16" height="16" aria-hidden="true" wire:loading wire:target="{{ $linkModel }}" />
            <span>{{ $message }}</span>
        </p>
        <button type="button" class="hm-pick__switch" wire:click="{{ $toSearch }}">{{ __('artist.store_back_to_search') }}</button>
    @else
        @php
            $status = $message !== '' ? $message : ($results === [] ? __('artist.store_hint', ['store' => $label]) : '');
        @endphp
        <p id="{{ $statusId }}" @class(['hm-pick__status', 'hm-pick__status--idle' => $status === '']) role="status"
            @if ($status === '' && $loadingTarget) wire:loading.class.remove="hm-pick__status--idle" wire:target="{{ $loadingTarget }}" @endif>
            @if ($loadingTarget)
                <x-lucide-loader-circle class="hm-icon hm-spin" width="16" height="16" aria-hidden="true" wire:loading wire:target="{{ $loadingTarget }}" />
            @endif
            <span>{{ $status }}</span>
        </p>

        @if ($results !== [])
            <ul class="hm-pick__list" aria-label="{{ __('artist.'.$store.'_results') }}">
                @foreach ($results as $result)
                    <li class="hm-pick__item" wire:key="{{ $linkId }}-{{ $result['id'] }}">
                        @if (! empty($result['image_url']))
                            <img src="{{ $result['image_url'] }}" alt="" width="40" height="40" class="hm-pick__avatar" loading="lazy" referrerpolicy="no-referrer">
                        @else
                            <span class="hm-pick__avatar" aria-hidden="true"><x-lucide-user-round class="hm-icon" width="18" height="18" /></span>
                        @endif
                        <span class="hm-pick__name">
                            {{ $result['name'] }}
                            @if (! empty($result['genre']))<span class="hm-pick__sub">{{ $result['genre'] }}</span>@endif
                        </span>
                        <a href="{{ $result['url'] }}" target="_blank" rel="noopener noreferrer" class="hm-btn hm-btn--ghost hm-btn--s hm-btn--icon">
                            <x-lucide-external-link class="hm-icon" width="16" height="16" aria-hidden="true" />
                            <span class="hm-sr">{{ __('artist.'.$store.'_open') }}: {{ $result['name'] }}</span>
                        </a>
                        <x-ui.button size="s" wire:click="{{ $selectAction }}({{ $selectArgs !== '' ? $selectArgs.', ' : '' }}'{{ $result['id'] }}')">
                            {{ __('artist.select') }}<span class="hm-sr">: {{ $result['name'] }}</span>
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif

        <button type="button" class="hm-pick__switch" wire:click="{{ $toLink }}">{{ __('artist.store_not_listed') }}</button>
    @endif
</div>
