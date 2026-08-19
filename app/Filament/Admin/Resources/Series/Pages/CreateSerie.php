<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Series\Pages;

use App\Filament\Admin\Resources\Series\SerieResource;
use App\Models\Serie;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Facturacion\Application\CasosDeUso\CrearSerie as CrearSerieCasoDeUso;
use Modules\Facturacion\Application\DTO\CrearSerieInput;

final class CreateSerie extends CreateRecord
{
    protected static string $resource = SerieResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $serie = app(CrearSerieCasoDeUso::class)->ejecutar(new CrearSerieInput(
            empresaId: (string) $data['empresa_id'],
            tipoComprobante: (string) $data['tipo_comprobante'],
            serie: mb_strtoupper((string) $data['serie']),
        ));

        return Serie::query()->findOrFail($serie->id());
    }
}
