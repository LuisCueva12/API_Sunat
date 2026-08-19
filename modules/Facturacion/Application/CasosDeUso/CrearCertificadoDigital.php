<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearCertificadoDigitalInput;
use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;
use Modules\Facturacion\Domain\Empresa\CertificadoEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\RepositorioCertificado;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;

final class CrearCertificadoDigital
{
    public function __construct(
        private readonly RepositorioCertificado $repositorio,
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly GeneradorId $generadorId,
        private readonly AnalizadorCertificadoDigital $analizador,
        private readonly GestorTransacciones $transacciones,
    ) {}

    public function ejecutar(CrearCertificadoDigitalInput $input): CertificadoEmpresa
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        $preparado = $this->analizador->preparar($input->contenido, $input->password);

        return $this->transacciones->ejecutar(function () use ($input, $preparado) {
            $activo = $this->repositorio->buscarActivoPorEmpresa($input->empresaId);

            if ($activo !== null) {
                $activo->reemplazar();
                $this->repositorio->guardar($activo);
            }

            $certificado = CertificadoEmpresa::registrar(
                id: $this->generadorId->nuevo(),
                empresaId: $input->empresaId,
                alias: $input->alias,
                contenidoPem: $preparado->contenidoPem,
                huellaSha256: $preparado->datos->huellaSha256,
                fechaEmision: $preparado->datos->fechaEmision,
                fechaExpiracion: $preparado->datos->fechaExpiracion,
            );

            $this->repositorio->guardar($certificado);

            return $certificado;
        });
    }
}
