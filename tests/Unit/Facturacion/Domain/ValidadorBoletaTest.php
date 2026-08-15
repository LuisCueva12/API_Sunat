<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Validacion\ValidadorBoleta;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function boletaDePrueba(TipoDocumentoIdentidad $tipoDocumento, string $numeroDocumento, bool $conItem = true): Comprobante
{
    $documento = $tipoDocumento === TipoDocumentoIdentidad::Ruc
        ? new DocumentoIdentidad($tipoDocumento, (string) new Ruc($numeroDocumento))
        : new DocumentoIdentidad($tipoDocumento, $numeroDocumento);

    $comprobante = Comprobante::registrar(
        id: '0199-test-uuid',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::Boleta,
        numero: new NumeroComprobante(new Serie('B001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: $documento,
        receptorRazonSocial: 'Cliente Final',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
    );

    if ($conItem) {
        $comprobante->agregarItem(new ItemComprobante(
            numeroOrden: 1,
            descripcion: 'Producto',
            unidadMedida: 'NIU',
            cantidad: 1.0,
            valorUnitario: Dinero::desde('10.00'),
            precioUnitario: Dinero::desde('11.80'),
            tipoAfectacionIgv: '10',
            montoIgv: Dinero::desde('1.80'),
            montoValorVenta: Dinero::desde('10.00'),
            descuento: Dinero::cero(),
        ));
        $comprobante->definirTotales(new TotalesComprobante(
            opGravada: Dinero::desde('10.00'),
            opExonerada: Dinero::cero(),
            opInafecta: Dinero::cero(),
            opGratuita: Dinero::cero(),
            totalIgv: Dinero::desde('1.80'),
            totalDescuentos: Dinero::cero(),
            total: Dinero::desde('11.80'),
        ));
    }

    return $comprobante;
}

it('acepta una boleta con DNI', function () {
    (new ValidadorBoleta)->validar(boletaDePrueba(TipoDocumentoIdentidad::Dni, '12345678'));
})->throwsNoExceptions();

it('acepta una boleta sin documento del receptor', function () {
    (new ValidadorBoleta)->validar(boletaDePrueba(TipoDocumentoIdentidad::SinDocumento, ''));
})->throwsNoExceptions();

it('rechaza una boleta con receptor identificado por RUC', function () {
    (new ValidadorBoleta)->validar(boletaDePrueba(TipoDocumentoIdentidad::Ruc, '20100070970'));
})->throws(ComprobanteInvalidoException::class);

it('rechaza una boleta sin items', function () {
    (new ValidadorBoleta)->validar(boletaDePrueba(TipoDocumentoIdentidad::Dni, '12345678', conItem: false));
})->throws(ComprobanteInvalidoException::class);
