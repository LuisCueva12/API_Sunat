<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Domain\Excepciones\IntegracionApiInvalidaException;
use Modules\Facturacion\Domain\Puertos\GestorClientesOAuth;
use Modules\Facturacion\Domain\Puertos\RepositorioIntegracionApi;

final class RevocarIntegracionApi
{
    public function __construct(
        private readonly RepositorioIntegracionApi $repositorio,
        private readonly GestorClientesOAuth $gestorClientes,
    ) {}

    public function ejecutar(string $empresaId, string $integracionId): void
    {
        $integracion = $this->repositorio->buscarPorId($empresaId, $integracionId);

        if ($integracion === null) {
            throw new IntegracionApiInvalidaException("No existe la integración {$integracionId} para esta empresa.");
        }

        $integracion->revocar();

        $this->gestorClientes->revocar($integracion->id());
        $this->repositorio->guardar($integracion);
    }
}
