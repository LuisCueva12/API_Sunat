<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Establecimientos\Pages;

use App\Filament\Admin\Resources\Establecimientos\EstablecimientoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEstablecimientos extends ListRecords
{
    protected static string $resource = EstablecimientoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
