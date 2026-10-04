<?php

namespace App\Filament\Resources\ImpersonationLogs\Pages;

use App\Filament\Resources\ImpersonationLogs\ImpersonationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListImpersonationLogs extends ListRecords
{
    protected static string $resource = ImpersonationLogResource::class;
}
