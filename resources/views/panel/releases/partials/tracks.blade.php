{{-- Salt okunur parça listesi; $release->tracks (audio, artists) ve $release->artists yüklü olmalı. --}}
@use('App\Enums\MediaStatus')
@use('App\Support\Format')

@php
    $primaryLine = $release->artists->where('role', 'primary')->pluck('name')->implode(', ');
@endphp

<ol class="m-0 list-none border-t border-line p-0">
    @foreach ($release->tracks as $track)
        @php($featuring = $track->featuringLine())
        <li class="hm-track">
            <span aria-hidden="true"></span>
            <span class="hm-track__pos">{{ Format::position($track->position) }}</span>
            <div class="hm-track__main">
                <span class="hm-track__title">
                    {{ $track->title ?: __('release.track.untitled') }}@if ($track->version)<span class="hm-track__version"> ({{ $track->version }})</span>@endif
                    @if ($track->explicit)<span class="hm-explicit" aria-hidden="true">E</span><span class="hm-sr">{{ __('release.info.explicit') }}</span>@endif
                </span>
                <span class="hm-track__artists">{{ $primaryLine }}{{ $featuring !== '' ? ' feat. '.$featuring : '' }}</span>
            </div>
            <span @class(['hm-track__isrc', 'is-empty' => ! $track->isrc])>{{ $track->formattedIsrc() ?? __('release.isrc_pending') }}</span>
            <span class="hm-track__dur">
                @if ($track->audio?->validation_status === MediaStatus::Pending)
                    <x-lucide-loader-circle class="hm-icon hm-spin inline" width="16" height="16" aria-hidden="true" /><span class="hm-sr">{{ __('release.tracks.audio.processing') }}</span>
                @elseif ($track->audio === null || $track->audio->validation_status === MediaStatus::Invalid)
                    <span aria-hidden="true">—</span>
                @else
                    {{ Format::duration($track->duration_ms) }}
                @endif
            </span>
        </li>
    @endforeach
</ol>
