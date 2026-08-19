<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Clientes\Pages;

use App\Filament\Admin\Resources\Clientes\ClienteResource;
use App\Models\Cliente;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Clientes\Application\CasosDeUso\CrearCliente as CrearClienteCasoDeUso;
use Modules\Clientes\Application\DTO\CrearClienteInput;

final class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $cliente = app(CrearClienteCasoDeUso::class)->ejecutar(new CrearClienteInput(
                empresaId: (string) $data['empresa_id'],
                tipoDocumento: (string) $data['tipo_documento'],
                numeroDocumento: (string) $data['numero_documento'],
                razonSocial: (string) $data['razon_social'],
                direccion: filled($data['direccion'] ?? null) ? (string) $data['direccion'] : null,
                email: filled($data['email'] ?? null) ? (string) $data['email'] : null,
            ));
        } catch (InvalidArgumentException|DomainException $e) {
            Notification::make()
                ->title('No se pudo crear el cliente')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        return Cliente::query()->findOrFail($cliente->id());
    }
}
