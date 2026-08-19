<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use Filament\Resources\Pages\EditRecord;

final class EditEmpresa extends EditRecord
{
    protected static string $resource = EmpresaResource::class;
}
