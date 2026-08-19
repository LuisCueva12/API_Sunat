<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\Empresa;
use DateTimeImmutable;
use Laravel\Passport\Passport;
use Modules\Facturacion\Domain\Empresa\EstadoIntegracionApi;
use Modules\Facturacion\Domain\Empresa\IntegracionApi;
use Modules\Facturacion\Domain\Puertos\RepositorioIntegracionApi;

final class RepositorioIntegracionApiEloquent implements RepositorioIntegracionApi
{
    public function guardar(IntegracionApi $integracion): void
    {
        Passport::client()->newQuery()->whereKey($integracion->id())->update([
            'name' => $integracion->nombre(),
            'scopes' => $integracion->scopes(),
            'revoked' => $integracion->estado() === EstadoIntegracionApi::Revocada,
        ]);
    }

    public function buscarPorId(string $empresaId, string $id): ?IntegracionApi
    {
        $cliente = Passport::client()->newQuery()
            ->whereKey($id)
            ->where('owner_type', Empresa::class)
            ->where('owner_id', $empresaId)
            ->first();

        if ($cliente === null) {
            return null;
        }

        return IntegracionApi::reconstituir(
            id: (string) $cliente->getKey(),
            empresaId: (string) $cliente->owner_id,
            nombre: $cliente->name,
            scopes: $cliente->scopes ?? [],
            estado: $cliente->revoked ? EstadoIntegracionApi::Revocada : EstadoIntegracionApi::Activa,
            ultimoUsoEn: $cliente->getAttribute('ultimo_uso_at') !== null
                ? new DateTimeImmutable((string) $cliente->getAttribute('ultimo_uso_at'))
                : null,
        );
    }
}
