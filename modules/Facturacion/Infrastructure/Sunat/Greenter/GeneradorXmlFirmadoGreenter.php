<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use DOMDocument;
use Greenter\Xml\Builder\InvoiceBuilder;
use Greenter\XMLSecLibs\Sunat\SignedXml;
use LogicException;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use RuntimeException;

final class GeneradorXmlFirmadoGreenter implements GeneradorXmlFirmado
{
    private const CBC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

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

        $xml = (new InvoiceBuilder)->build($documento);

        if ($xml === '') {
            throw new RuntimeException("Greenter no pudo generar el XML del comprobante {$comprobante->id()}.");
        }

        $xml = $this->agregarTipoOperacion($xml, $documento->getTipoOperacion() ?? '');

        $firmador = new SignedXml;
        $firmador->setCertificate($certificado->contenidoPem);
        $xmlFirmado = $firmador->signXml($xml);

        if ($xmlFirmado === '') {
            throw new RuntimeException("Greenter no pudo generar/firmar el XML del comprobante {$comprobante->id()}.");
        }

        return $xmlFirmado;
    }

    private function agregarTipoOperacion(string $xml, string $tipoOperacion): string
    {
        $documento = new DOMDocument;

        if (! $documento->loadXML($xml)) {
            throw new RuntimeException('Greenter generó un XML UBL inválido antes de firmarlo.');
        }

        $personalizacion = $documento->getElementsByTagNameNS(self::CBC_NAMESPACE, 'CustomizationID')->item(0);

        if ($personalizacion === null || $personalizacion->parentNode === null) {
            throw new RuntimeException('El XML UBL no contiene CustomizationID.');
        }

        $perfil = $documento->createElementNS(self::CBC_NAMESPACE, 'cbc:ProfileID', $tipoOperacion);
        $perfil->setAttribute('schemeName', 'SUNAT:Identificador de Tipo de Operación');
        $perfil->setAttribute('schemeAgencyName', 'PE:SUNAT');
        $perfil->setAttribute('schemeURI', 'urn:pe:gob:sunat:cpe:see:gem:catalogos:catalogo17');

        $personalizacion->parentNode->insertBefore($perfil, $personalizacion->nextSibling);

        $resultado = $documento->saveXML();

        if ($resultado === false) {
            throw new RuntimeException('No se pudo serializar el XML UBL con su tipo de operación.');
        }

        return $resultado;
    }
}
