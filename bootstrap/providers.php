<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    FortifyServiceProvider::class,
    AdminPanelProvider::class,
];
