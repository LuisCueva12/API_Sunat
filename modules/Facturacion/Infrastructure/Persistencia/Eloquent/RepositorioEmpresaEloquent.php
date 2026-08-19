<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Database\QueryException;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\EstadoEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

final class RepositorioEmpresaEloquent implements RepositorioEmpresa
{
    private const CODIGO_UNIQUE_VIOLATION = '23505';

    public function guardar(Empresa $empresa): void
    {
        try {
            EmpresaEloquent::query()->updateOrCreate(
                ['id' => $empresa->id()],
                [
                    'ruc' => $empresa->ruc()->valor(),
                    'razon_social' => $empresa->razonSocial(),
                    'nombre_comercial' => $empresa->nombreComercial(),
                    'estado' => $empresa->estado()->value,
                ],
            );
        } catch (QueryException $e) {
            if ($e->getCode() === self::CODIGO_UNIQUE_VIOLATION) {
                throw new EmpresaInvalidaException("Ya existe una empresa registrada con el RUC {$empresa->ruc()->valor()}.");
            }

            throw $e;
        }
    }

    public function buscarPorId(string $id): ?Empresa
    {
        $fila = EmpresaEloquent::query()->find($id);

        return $fila === null ? null : $this->aDominio($fila);
    }

    public function buscarPorRuc(string $ruc): ?Empresa
    {
        $fila = EmpresaEloquent::query()->where('ruc', $ruc)->first();

        return $fila === null ? null : $this->aDominio($fila);
    }

    private function aDominio(EmpresaEloquent $fila): Empresa
    {
        return Empresa::reconstituir(
            id: $fila->id,
            ruc: new Ruc($fila->ruc),
            razonSocial: $fila->razon_social,
            nombreComercial: $fila->nombre_comercial,
            estado: EstadoEmpresa::from($fila->estado),
        );
    }
}
