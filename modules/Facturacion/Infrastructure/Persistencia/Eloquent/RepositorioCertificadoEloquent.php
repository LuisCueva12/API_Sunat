<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\CertificadoDigital as CertificadoEloquent;
use DateTimeImmutable;
use Modules\Facturacion\Domain\Empresa\CertificadoEmpresa;
use Modules\Facturacion\Domain\Empresa\EstadoCertificado;
use Modules\Facturacion\Domain\Puertos\RepositorioCertificado;

final class RepositorioCertificadoEloquent implements RepositorioCertificado
{
    public function guardar(CertificadoEmpresa $certificado): void
    {
        CertificadoEloquent::query()->updateOrCreate(
            ['id' => $certificado->id()],
            [
                'empresa_id' => $certificado->empresaId(),
                'alias' => $certificado->alias(),
                'contenido_cifrado' => $certificado->contenidoPem(),
                'password_cifrado' => null,
                'huella_sha256' => $certificado->huellaSha256(),
                'fecha_emision' => $certificado->fechaEmision(),
                'fecha_expiracion' => $certificado->fechaExpiracion(),
                'estado' => $certificado->estado()->value,
            ],
        );
    }

    public function buscarActivoPorEmpresa(string $empresaId): ?CertificadoEmpresa
    {
        $fila = CertificadoEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', EstadoCertificado::Activo->value)
            ->first();

        return $fila === null ? null : $this->aDominio($fila);
    }

    private function aDominio(CertificadoEloquent $fila): CertificadoEmpresa
    {
        return CertificadoEmpresa::reconstituir(
            id: $fila->id,
            empresaId: $fila->empresa_id,
            alias: $fila->alias,
            contenidoPem: $fila->contenido_cifrado,
            huellaSha256: $fila->huella_sha256,
            fechaEmision: $fila->fecha_emision !== null ? DateTimeImmutable::createFromInterface($fila->fecha_emision) : null,
            fechaExpiracion: DateTimeImmutable::createFromInterface($fila->fecha_expiracion),
            estado: EstadoCertificado::from($fila->estado),
        );
    }
}
