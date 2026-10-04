<?php

namespace App\Filament\Resources\Genres\Pages;

use App\Filament\Resources\Genres\GenreResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGenres extends ManageRecords
{
    protected static string $resource = GenreResource::class;
}
