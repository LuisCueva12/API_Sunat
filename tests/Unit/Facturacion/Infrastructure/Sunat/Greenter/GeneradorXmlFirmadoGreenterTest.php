<?php

declare(strict_types=1);

use Greenter\Model\Sale\Invoice;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;
use Modules\Facturacion\Infrastructure\Sunat\Greenter\GeneradorXmlFirmadoGreenter;
use Modules\Facturacion\Infrastructure\Sunat\Greenter\MapeadorFacturaGreenter;

function certificadoDePruebaAutofirmado(): CertificadoDigital
{
    $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    $csr = openssl_csr_new(['commonName' => 'Prueba Local'], $llave);
    $cert = openssl_csr_sign($csr, null, $llave, 365);

    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($llave, $keyPem);

    return new CertificadoDigital($certPem.$keyPem);
}

function comprobanteFacturaCompleto(): Comprobante
{
    $comprobante = Comprobante::registrar(
        id: '01991111-2222-7333-8444-555566667777',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, (string) new Ruc('20100070970')),
        receptorRazonSocial: 'Cliente de Prueba SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
    );

    $comprobante->agregarItem(new ItemComprobante(
        numeroOrden: 1,
        descripcion: 'Servicio de consultoría',
        unidadMedida: 'NIU',
        cantidad: 2.0,
        valorUnitario: Dinero::desde('100.00'),
        precioUnitario: Dinero::desde('118.00'),
        tipoAfectacionIgv: '10',
        montoIgv: Dinero::desde('36.00'),
        montoValorVenta: Dinero::desde('200.00'),
        descuento: Dinero::cero(),
    ));

    $comprobante->definirTotales(new TotalesComprobante(
        opGravada: Dinero::desde('200.00'),
        opExonerada: Dinero::cero(),
        opInafecta: Dinero::cero(),
        opGratuita: Dinero::cero(),
        totalIgv: Dinero::desde('36.00'),
        totalDescuentos: Dinero::cero(),
        total: Dinero::desde('236.00'),
    ));

    return $comprobante;
}

it('mapea una factura del dominio a un Invoice de Greenter sin errores', function () {
    $mapeador = new MapeadorFacturaGreenter;
    $emisor = new DatosEmisor(
        ruc: new Ruc('20100070970'),
        razonSocial: 'Empresa de Prueba SAC',
        nombreComercial: null,
        direccion: 'Av. Prueba 123',
        ubigeo: '150101',
    );

    $invoice = $mapeador->mapear(comprobanteFacturaCompleto(), $emisor);

    expect($invoice->getSerie())->toBe('F001')
        ->and($invoice->getCorrelativo())->toBe('1')
        ->and($invoice->getMtoImpVenta())->toBe(236.0)
        ->and($invoice->getDetails())->toHaveCount(1)
        ->and($invoice->getLegends())->toHaveCount(1)
        ->and($invoice->getLegends()[0]->getValue())->toContain('DOSCIENTOS TREINTA Y SEIS')
        ->and($invoice->getLegends()[0]->getValue())->toContain('SOLES');
})->skip(fn () => ! class_exists(Invoice::class), 'Requiere greenter/greenter instalado.');

it('genera un XML firmado bien formado a partir de una factura', function () {
    $mapeador = new MapeadorFacturaGreenter;
    $generador = new GeneradorXmlFirmadoGreenter($mapeador);

    $emisor = new DatosEmisor(
        ruc: new Ruc('20100070970'),
        razonSocial: 'Empresa de Prueba SAC',
        nombreComercial: null,
        direccion: 'Av. Prueba 123',
        ubigeo: '150101',
    );

    $xml = $generador->generar(comprobanteFacturaCompleto(), $emisor, certificadoDePruebaAutofirmado());

    expect($xml)->toBeString()->not->toBeEmpty();

    $documento = new DOMDocument;
    $cargado = $documento->loadXML($xml);

    expect($cargado)->toBeTrue('El XML generado debe ser XML bien formado')
        ->and($xml)->toContain('Invoice')
        ->and($xml)->toContain('<ds:Signature');
})->skip(fn () => ! extension_loaded('soap'), 'Greenter\\See instancia su cliente SOAP en el constructor incluso para solo firmar XML — requiere ext-soap aunque no se envíe nada a SUNAT.');
