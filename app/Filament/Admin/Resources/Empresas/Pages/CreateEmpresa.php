<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Empresas\Pages;

use App\Filament\Admin\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Facturacion\Application\CasosDeUso\CrearEmpresa as CrearEmpresaCasoDeUso;
use Modules\Facturacion\Application\DTO\CrearEmpresaInput;

final class CreateEmpresa extends CreateRecord
{
    protected static string $resource = EmpresaResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $empresa = app(CrearEmpresaCasoDeUso::class)->ejecutar(new CrearEmpresaInput(
                ruc: (string) $data['ruc'],
                razonSocial: (string) $data['razon_social'],
                nombreComercial: filled($data['nombre_comercial'] ?? null) ? (string) $data['nombre_comercial'] : null,
            ));
        } catch (InvalidArgumentException|DomainException $e) {
            Notification::make()
                ->title('No se pudo crear la empresa')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        return Empresa::query()->findOrFail($empresa->id());
    }
}
