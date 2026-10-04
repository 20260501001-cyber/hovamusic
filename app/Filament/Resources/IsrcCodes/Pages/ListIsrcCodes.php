<?php

namespace App\Filament\Resources\IsrcCodes\Pages;

use App\Domain\Isrc\IsrcAllocator;
use App\Filament\Resources\IsrcCodes\IsrcCodeResource;
use App\Models\IsrcCode;
use Filament\Resources\Pages\ListRecords;

class ListIsrcCodes extends ListRecords
{
    protected static string $resource = IsrcCodeResource::class;

    public function getSubheading(): ?string
    {
        $allocator = app(IsrcAllocator::class);

        if (! $allocator->isEnabled()) {
            return 'ISRC öneki tanımlı değil; Ayarlar sayfasından gir.';
        }

        $next = $allocator->nextCode();
        $year = (int) now()->format('y');
        $used = IsrcCode::query()->where('year', $year)->count();

        return sprintf(
            'Önek: %s · Bu yıl atanan: %d · Sıradaki kod: %s',
            $allocator->registrant(),
            $used,
            $next ? substr($next, 0, 2).'-'.substr($next, 2, 3).'-'.substr($next, 5, 2).'-'.substr($next, 7) : 'yok (yıllık sınır doldu)',
        );
    }
}
