<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes\Pages;

use App\Filament\Empresa\Resources\Comprobantes\ComprobanteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListComprobantes extends ListRecords
{
    protected static string $resource = ComprobanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Emitir comprobante')
                ->icon(Heroicon::OutlinedPlusCircle),
        ];
    }
}
