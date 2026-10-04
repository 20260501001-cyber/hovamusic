{{-- Yayın satırı: satırın tamamı tek bağlantı. $release (cover, artists, tracks) yüklü olmalı. --}}
@php
    $coverUrl = $release->cover?->isValid() ? \App\Domain\Media\MediaUrl::temporary($release->cover) : null;
    $meta = collect([
        $release->artistLine() ?: null,
        $release->type?->label(),
        $release->release_date ? \App\Support\Format::longDate($release->release_date) : null,
    ])->filter()->implode(' · ');
@endphp

<a href="{{ route('panel.releases.show', $release) }}" class="hm-release">
    @if ($coverUrl)
        <span class="hm-cover" style="background-image: url('{{ $coverUrl }}')" aria-hidden="true"></span>
    @else
        <span class="hm-cover" aria-hidden="true"><x-lucide-disc class="hm-icon" width="20" height="20" /></span>
    @endif
    <span class="hm-release__main">
        <span class="hm-release__title">{{ $release->displayTitle() }}</span>
        <span class="hm-release__meta">{{ $meta ?: __('release.statuses.draft') }}</span>
    </span>
    <span class="hm-release__code">{{ $release->upc ?: __('release.upc_pending') }}</span>
    <span class="hm-release__status"><x-ui.status-badge :status="$release->status" /></span>
</a>
