{{-- Admin yayın detayı: kapak dosyasının bilgisi ve indirme. --}}
@php($cover = $getRecord()?->cover)

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($cover === null)
        <p class="text-sm text-gray-500 dark:text-gray-400">Kapak yüklenmemiş.</p>
    @else
        <div class="grid gap-2">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ strtoupper((string) $cover->format) }}@if ($cover->width) · {{ $cover->width }}×{{ $cover->height }} px @endif · {{ \App\Support\Format::bytes((int) $cover->size) }}
            </p>
            <div>
                <x-filament::button size="sm" color="gray" icon="lucide-download" wire:click="downloadMedia('{{ $cover->ulid }}')" wire:loading.attr="disabled">
                    Kapağı indir
                </x-filament::button>
            </div>
        </div>
    @endif
</x-dynamic-component>
