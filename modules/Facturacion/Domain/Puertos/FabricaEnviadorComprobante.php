<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;

/**
 * EnviadorComprobanteElectronico no se puede resolver directo del
 * contenedor: necesita usuario/clave/endpoint SOL, que solo se conocen en
 * tiempo de ejecución (son propios de cada empresa, no config fija). Esta
 * fábrica sí es un servicio fijo, bindable normalmente.
 */
interface FabricaEnviadorComprobante
{
    public function crear(DatosSunatEmpresa $datosSunat): EnviadorComprobanteElectronico;
}
