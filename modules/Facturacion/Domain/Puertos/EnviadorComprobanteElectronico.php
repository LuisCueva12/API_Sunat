<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;

interface EnviadorComprobanteElectronico
{
    /**
     * Envía un XML ya firmado a SUNAT. Recibe el XML como parámetro (no lo
     * regenera) porque el XML firmado ya debe estar persistido antes de
     * arriesgarse a la llamada de red — ver docs/01_ARQUITECTURA.md §6.
     */
    public function enviar(Comprobante $comprobante, string $xmlFirmado): ResultadoEnvio;
}
