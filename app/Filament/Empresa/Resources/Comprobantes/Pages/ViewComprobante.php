<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes\Pages;

use App\Filament\Empresa\Resources\Comprobantes\ComprobanteResource;
use App\Filament\Support\ComprobanteAcciones;
use App\Filament\Support\ComprobanteInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

final class ViewComprobante extends ViewRecord
{
    protected static string $resource = ComprobanteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComprobanteAcciones::descargarPdf(),
            ComprobanteAcciones::reintentar(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components(ComprobanteInfolist::secciones(mostrarDiagnosticoTecnico: false));
    }
}
