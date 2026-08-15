<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;

/**
 * Un solo puerto para generar y firmar el XML UBL 2.1, no dos separados:
 * Greenter (See::getXmlSigned) resuelve ambos pasos como una sola operación
 * atómica y no vale la pena forzar una separación artificial que la
 * librería no ofrece. Los eventos de dominio xml_generado/xml_firmado
 * igual se registran por separado al consumir el resultado — eso es una
 * decisión de trazabilidad, no de esta interfaz.
 */
interface GeneradorXmlFirmado
{
    public function generar(
        Comprobante $comprobante,
        DatosEmisor $emisor,
        CertificadoDigital $certificado,
    ): string;
}
