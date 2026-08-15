<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\See;
use LogicException;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use RuntimeException;

final class GeneradorXmlFirmadoGreenter implements GeneradorXmlFirmado
{
    public function __construct(
        private readonly MapeadorFacturaGreenter $mapeadorFactura,
    ) {}

    public function generar(Comprobante $comprobante, DatosEmisor $emisor, CertificadoDigital $certificado): string
    {
        $documento = match ($comprobante->tipo()) {
            TipoComprobante::Factura => $this->mapeadorFactura->mapear($comprobante, $emisor),
            default => throw new LogicException(
                "Tipo de comprobante '{$comprobante->tipo()->value}' aún no soportado por GeneradorXmlFirmadoGreenter."
            ),
        };

        $see = new See;
        $see->setCertificate($certificado->contenidoPem);

        $xmlFirmado = $see->getXmlSigned($documento);

        if ($xmlFirmado === null || $xmlFirmado === '') {
            throw new RuntimeException("Greenter no pudo generar/firmar el XML del comprobante {$comprobante->id()}.");
        }

        return $xmlFirmado;
    }
}
