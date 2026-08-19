<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ClientesServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\EmpresaPanelProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    ClientesServiceProvider::class,
    AdminPanelProvider::class,
    EmpresaPanelProvider::class,
];
