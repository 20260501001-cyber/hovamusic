<?php

namespace App\Filament\Resources\AdminAllowedIps\Pages;

use App\Filament\Resources\AdminAllowedIps\AdminAllowedIpResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAdminAllowedIps extends ManageRecords
{
    protected static string $resource = AdminAllowedIpResource::class;
}
