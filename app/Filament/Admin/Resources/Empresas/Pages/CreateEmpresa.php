<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Facturacion\Application\CasosDeUso\CrearEmpresa as CrearEmpresaCasoDeUso;
use Modules\Facturacion\Application\DTO\CrearEmpresaInput;

final class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $empresa = app(CrearEmpresaCasoDeUso::class)->ejecutar(new CrearEmpresaInput(
            ruc: (string) $data['ruc'],
            razonSocial: (string) $data['razon_social'],
            nombreComercial: filled($data['nombre_comercial'] ?? null) ? (string) $data['nombre_comercial'] : null,
        ));

        return Empresa::query()->findOrFail($empresa->id());
    }
}
