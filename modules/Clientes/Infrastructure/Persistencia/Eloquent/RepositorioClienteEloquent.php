<?php

declare(strict_types=1);

namespace Modules\Clientes\Infrastructure\Persistencia\Eloquent;

use App\Models\Cliente as ClienteEloquent;
use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;

final class RepositorioClienteEloquent implements RepositorioCliente
{
    public function guardar(Cliente $cliente): void
    {
        ClienteEloquent::query()->updateOrCreate(
            ['id' => $cliente->id()],
            [
                'empresa_id' => $cliente->empresaId(),
                'tipo_documento' => $cliente->tipoDocumento()->value,
                'numero_documento' => $cliente->numeroDocumento(),
                'razon_social' => $cliente->razonSocial(),
                'direccion' => $cliente->direccion(),
                'email' => $cliente->email(),
            ],
        );
    }

    public function buscarPorId(string $empresaId, string $id): ?Cliente
    {
        $fila = ClienteEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('id', $id)
            ->first();

        if ($fila === null) {
            return null;
        }

        return $this->aDominio($fila);
    }

    public function existe(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): bool
    {
        return ClienteEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_documento', $tipoDocumento->value)
            ->where('numero_documento', $numeroDocumento)
            ->exists();
    }

    private function aDominio(ClienteEloquent $fila): Cliente
    {
        return Cliente::reconstituir(
            id: $fila->id,
            empresaId: $fila->empresa_id,
            tipoDocumento: TipoDocumentoCliente::from($fila->tipo_documento),
            numeroDocumento: $fila->numero_documento,
            razonSocial: $fila->razon_social,
            direccion: $fila->direccion,
            email: $fila->email,
        );
    }
}
