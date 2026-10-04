{{-- Admin yayın detayı: parçayı tarayıcıda dinleme ve orijinal dosyayı indirme. --}}
@php
    $track = $getRecord();
    $audio = $track?->audio;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($audio === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">Ses dosyası yüklenmemiş.</p>
    @else
        <div class="flex flex-wrap items-center gap-3">
            @if ($audio->isValid())
                <audio controls preload="none" class="h-10 w-full max-w-md" src="{{ \App\Domain\Media\MediaUrl::temporary($audio) }}">
                    Tarayıcın ses oynatmayı desteklemiyor.
                </audio>
            @endif
            <x-filament::button size="sm" color="gray" icon="lucide-download" wire:click="downloadMedia('{{ $audio->ulid }}')" wire:loading.attr="disabled">
                Dosyayı indir
            </x-filament::button>
        </div>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ $audio->original_name }} · {{ \App\Support\Format::bytes((int) $audio->size) }}@if ($audio->audioSummary() !== '') · {{ $audio->audioSummary() }}@endif
            @unless ($audio->isValid())
                · <span class="text-danger-600 dark:text-danger-400">Dosya kurallara uymuyor ya da hâlâ kontrol ediliyor.</span>
            @endunless
        </p>
    @endif
</x-dynamic-component>
