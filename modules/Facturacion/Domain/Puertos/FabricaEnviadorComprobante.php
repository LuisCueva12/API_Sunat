<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;

interface FabricaEnviadorComprobante
{
    public function crear(DatosSunatEmpresa $datosSunat): EnviadorComprobanteElectronico;
}
