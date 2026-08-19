<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Establecimientos\Pages;

use App\Filament\Admin\Resources\Establecimientos\EstablecimientoResource;
use App\Models\Establecimiento;
use Filament\Resources\Pages\EditRecord;

final class EditEstablecimiento extends EditRecord
{
    protected static string $resource = EstablecimientoResource::class;

    protected function afterSave(): void
    {
        /** @var Establecimiento $establecimiento */
        $establecimiento = $this->record;
        $establecimiento->asegurarPrincipalUnico();
    }
}
