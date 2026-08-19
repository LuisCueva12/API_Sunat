<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;


interface GeneradorXmlFirmado
{
    public function generar(
        Comprobante $comprobante,
        DatosEmisor $emisor,
        CertificadoDigital $certificado,
    ): string;
}
