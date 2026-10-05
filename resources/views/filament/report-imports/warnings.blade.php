{{-- Önizleme uyarıları: engelleyenler çözülmeden rapor onaylanamaz. --}}
@php($warnings = $getRecord()?->warnings ?? [])

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="grid gap-3">
        @foreach ($warnings['blocking'] ?? [] as $warning)
            <div class="flex items-start gap-2 text-sm text-danger-600 dark:text-danger-400">
                <x-filament::icon icon="lucide-octagon-alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <span><strong>Onayı engelliyor:</strong> {{ $warning }}</span>
            </div>
        @endforeach
        @foreach ($warnings['notes'] ?? [] as $note)
            <div class="flex items-start gap-2 text-sm text-warning-600 dark:text-warning-400">
                <x-filament::icon icon="lucide-triangle-alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>{{ $note }}</span>
            </div>
        @endforeach
    </div>
</x-dynamic-component>
