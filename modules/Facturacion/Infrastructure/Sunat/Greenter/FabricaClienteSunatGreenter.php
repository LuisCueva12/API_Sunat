<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;
use Modules\Facturacion\Domain\Puertos\EnviadorComprobanteElectronico;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;

final class FabricaClienteSunatGreenter implements FabricaEnviadorComprobante
{
    public function crear(DatosSunatEmpresa $datosSunat): EnviadorComprobanteElectronico
    {
        return new ClienteSunatGreenter(
            usuarioSol: $datosSunat->usuarioSol,
            claveSol: $datosSunat->claveSol,
            endpoint: $datosSunat->endpoint,
        );
    }
}
