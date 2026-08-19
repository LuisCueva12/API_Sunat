<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\CredencialSunat as CredencialEloquent;
use Modules\Facturacion\Domain\Empresa\CredencialSunatEmpresa;
use Modules\Facturacion\Domain\Empresa\EntornoSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioCredencialSunat;

final class RepositorioCredencialSunatEloquent implements RepositorioCredencialSunat
{
    public function guardar(CredencialSunatEmpresa $credencial): void
    {
        CredencialEloquent::query()->updateOrCreate(
            ['id' => $credencial->id()],
            [
                'empresa_id' => $credencial->empresaId(),
                'entorno' => $credencial->entorno()->value,
                'usuario_sol_cifrado' => $credencial->usuarioSol(),
                'clave_sol_cifrada' => $credencial->claveSol(),
                'estado' => $credencial->estaActiva() ? 'ACTIVA' : 'INACTIVA',
            ],
        );
    }

    public function buscarPorEmpresaYEntorno(string $empresaId, EntornoSunat $entorno): ?CredencialSunatEmpresa
    {
        $fila = CredencialEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('entorno', $entorno->value)
            ->first();

        return $fila === null ? null : $this->aDominio($fila);
    }

    private function aDominio(CredencialEloquent $fila): CredencialSunatEmpresa
    {
        return CredencialSunatEmpresa::reconstituir(
            id: $fila->id,
            empresaId: $fila->empresa_id,
            entorno: EntornoSunat::from($fila->entorno),
            usuarioSol: $fila->usuario_sol_cifrado,
            claveSol: $fila->clave_sol_cifrada,
            activa: $fila->estado === 'ACTIVA',
        );
    }
}
