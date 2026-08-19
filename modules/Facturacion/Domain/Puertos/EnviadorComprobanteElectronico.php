<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;

interface EnviadorComprobanteElectronico
{
    public function enviar(Comprobante $comprobante, string $xmlFirmado): ResultadoEnvio;
}
