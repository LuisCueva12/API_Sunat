<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat;

use App\Models\CertificadoDigital as CertificadoDigitalEloquent;
use App\Models\CredencialSunat as CredencialSunatEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;
use Modules\Facturacion\Domain\Excepciones\ConfiguracionSunatInvalidaException;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Infrastructure\Sunat\Greenter\ConfiguracionSunat;

/**
 * Único lugar de la aplicación que lee certificados_digitales/
 * credenciales_sunat y los descifra — todo lo que sale de aquí vive
 * transitoriamente en memoria durante el pipeline de envío, nunca se
 * vuelve a persistir en claro.
 */
final class ProveedorDatosSunatEloquent implements ProveedorDatosSunat
{
    public function paraEmpresa(string $empresaId, string $entorno): DatosSunatEmpresa
    {
        $empresa = EmpresaEloquent::query()->find($empresaId);

        if ($empresa === null) {
            throw new ConfiguracionSunatInvalidaException("No existe la empresa {$empresaId}.");
        }

        $certificado = CertificadoDigitalEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', 'ACTIVO')
            ->first();

        if ($certificado === null) {
            throw new ConfiguracionSunatInvalidaException("La empresa {$empresaId} no tiene un certificado digital activo.");
        }

        $credencial = CredencialSunatEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('entorno', $entorno)
            ->where('estado', 'ACTIVA')
            ->first();

        if ($credencial === null) {
            throw new ConfiguracionSunatInvalidaException(
                "La empresa {$empresaId} no tiene credenciales SUNAT activas para el entorno {$entorno}."
            );
        }

        return new DatosSunatEmpresa(
            emisor: new DatosEmisor(
                ruc: new Ruc($empresa->ruc),
                razonSocial: $empresa->razon_social,
                nombreComercial: $empresa->nombre_comercial,
                direccion: null,
                ubigeo: null,
            ),
            certificado: new CertificadoDigital($certificado->contenido_cifrado),
            usuarioSol: $credencial->usuario_sol_cifrado,
            claveSol: $credencial->clave_sol_cifrada,
            endpoint: ConfiguracionSunat::endpoint($entorno),
        );
    }
}
