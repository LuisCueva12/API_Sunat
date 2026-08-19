<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearIntegracionApiInput;
use Modules\Facturacion\Application\DTO\ResultadoCrearIntegracionApi;
use Modules\Facturacion\Domain\Empresa\IntegracionApi;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GestorClientesOAuth;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioIntegracionApi;

final class CrearIntegracionApi
{
    public function __construct(
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly RepositorioIntegracionApi $repositorio,
        private readonly GestorClientesOAuth $gestorClientes,
    ) {}

    public function ejecutar(CrearIntegracionApiInput $input): ResultadoCrearIntegracionApi
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        IntegracionApi::validarScopes($input->scopes);

        $cliente = $this->gestorClientes->crear($input->empresaId, $input->nombre, $input->scopes);

        $integracion = IntegracionApi::registrar(
            id: $cliente->clientId,
            empresaId: $input->empresaId,
            nombre: $input->nombre,
            scopes: $input->scopes,
        );

        $this->repositorio->guardar($integracion);

        return new ResultadoCrearIntegracionApi($integracion, $cliente->clientSecret);
    }
}
