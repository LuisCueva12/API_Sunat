<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Clientes\Pages;

use App\Filament\Empresa\Resources\Clientes\ClienteResource;
use App\Models\Cliente;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Clientes\Application\CasosDeUso\ActualizarCliente;
use Modules\Clientes\Application\DTO\ActualizarClienteInput;

final class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Cliente $record */
        try {
            app(ActualizarCliente::class)->ejecutar(new ActualizarClienteInput(
                empresaId: $record->empresa_id,
                clienteId: $record->id,
                razonSocial: (string) $data['razon_social'],
                direccion: filled($data['direccion'] ?? null) ? (string) $data['direccion'] : null,
                email: filled($data['email'] ?? null) ? (string) $data['email'] : null,
            ));
        } catch (InvalidArgumentException|DomainException $e) {
            Notification::make()
                ->title('No se pudo actualizar el cliente')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }

        return $record->refresh();
    }
}
