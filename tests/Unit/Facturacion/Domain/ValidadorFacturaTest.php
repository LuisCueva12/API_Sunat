<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Validacion\ValidadorFactura;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function facturaDePrueba(TipoDocumentoIdentidad $tipoDocumento = TipoDocumentoIdentidad::Ruc, string $numeroDocumento = '20100070970'): Comprobante
{
    $documento = $tipoDocumento === TipoDocumentoIdentidad::Ruc
        ? new DocumentoIdentidad($tipoDocumento, (string) new Ruc($numeroDocumento))
        : new DocumentoIdentidad($tipoDocumento, $numeroDocumento);

    return Comprobante::registrar(
        id: '0199-test-uuid',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: $documento,
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
    );
}

it('acepta una factura válida con RUC, items y total positivo', function () {
    $comprobante = facturaDePrueba();
    $comprobante->agregarItem(new ItemComprobante(
        numeroOrden: 1,
        descripcion: 'Servicio',
        unidadMedida: 'NIU',
        cantidad: 1.0,
        valorUnitario: Dinero::desde('100.00'),
        precioUnitario: Dinero::desde('118.00'),
        tipoAfectacionIgv: '10',
        montoIgv: Dinero::desde('18.00'),
        montoValorVenta: Dinero::desde('100.00'),
        descuento: Dinero::cero(),
    ));
    $comprobante->definirTotales(new TotalesComprobante(
        opGravada: Dinero::desde('100.00'),
        opExonerada: Dinero::cero(),
        opInafecta: Dinero::cero(),
        opGratuita: Dinero::cero(),
        totalIgv: Dinero::desde('18.00'),
        totalDescuentos: Dinero::cero(),
        total: Dinero::desde('118.00'),
    ));

    (new ValidadorFactura)->validar($comprobante);
})->throwsNoExceptions();

it('rechaza una factura sin RUC del receptor', function () {
    $comprobante = facturaDePrueba(TipoDocumentoIdentidad::Dni, '12345678');

    (new ValidadorFactura)->validar($comprobante);
})->throws(ComprobanteInvalidoException::class);

it('rechaza una factura sin items', function () {
    $comprobante = facturaDePrueba();

    (new ValidadorFactura)->validar($comprobante);
})->throws(ComprobanteInvalidoException::class);
