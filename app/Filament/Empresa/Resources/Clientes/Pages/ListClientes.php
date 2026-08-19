<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Clientes\Pages;

use App\Filament\Empresa\Resources\Clientes\ClienteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListClientes extends ListRecords
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
