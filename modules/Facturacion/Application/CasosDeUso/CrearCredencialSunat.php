<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearCredencialSunatInput;
use Modules\Facturacion\Domain\Empresa\CredencialSunatEmpresa;
use Modules\Facturacion\Domain\Empresa\EntornoSunat;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioCredencialSunat;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;

final class CrearCredencialSunat
{
    public function __construct(
        private readonly RepositorioCredencialSunat $repositorio,
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly GeneradorId $generadorId,
    ) {}

    public function ejecutar(CrearCredencialSunatInput $input): CredencialSunatEmpresa
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        $entorno = EntornoSunat::from($input->entorno);

        $existente = $this->repositorio->buscarPorEmpresaYEntorno($input->empresaId, $entorno);

        if ($existente !== null) {
            $existente->rotar($input->usuarioSol, $input->claveSol);
            $this->repositorio->guardar($existente);

            return $existente;
        }

        $credencial = CredencialSunatEmpresa::registrar(
            id: $this->generadorId->nuevo(),
            empresaId: $input->empresaId,
            entorno: $entorno,
            usuarioSol: $input->usuarioSol,
            claveSol: $input->claveSol,
        );

        $this->repositorio->guardar($credencial);

        return $credencial;
    }
}
