{{-- Rapor kuyrukta işlenirken sayfa kendini yeniler. --}}
<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div wire:poll.5s class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
        <x-filament::loading-indicator class="h-4 w-4" />
        <span>Rapor işleniyor. Önizleme hazır olunca bu sayfa güncellenir.</span>
    </div>
</x-dynamic-component>
