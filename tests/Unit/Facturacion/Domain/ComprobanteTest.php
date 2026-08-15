<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function comprobanteDePrueba(): Comprobante
{
    return Comprobante::registrar(
        id: '0199-test-uuid',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, (string) new Ruc('20100070970')),
        receptorRazonSocial: 'Cliente de Prueba SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
    );
}

it('se registra en estado REGISTRADO', function () {
    expect(comprobanteDePrueba()->estado())->toBe(EstadoComprobante::Registrado);
});

it('acumula items y calcula el total esperado', function () {
    $comprobante = comprobanteDePrueba();

    $comprobante->agregarItem(new ItemComprobante(
        numeroOrden: 1,
        descripcion: 'Servicio de prueba',
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

    expect($comprobante->items())->toHaveCount(1)
        ->and($comprobante->totales()->total->comoString())->toBe('118.00');
});

it('sigue el camino feliz hasta ACEPTADO', function () {
    $comprobante = comprobanteDePrueba();

    $comprobante->marcarProcesando();
    $comprobante->marcarAceptado();

    expect($comprobante->estado())->toBe(EstadoComprobante::Aceptado)
        ->and($comprobante->estado()->esTerminal())->toBeTrue();
});

it('permite reintentar solo desde ERROR', function () {
    $comprobante = comprobanteDePrueba();

    $comprobante->marcarProcesando();
    $comprobante->marcarError();

    expect($comprobante->esReintentable())->toBeTrue()
        ->and($comprobante->intentosEnvio())->toBe(1);

    $comprobante->reintentar();

    expect($comprobante->estado())->toBe(EstadoComprobante::Procesando);
});

it('nunca permite reintentar un comprobante RECHAZADO', function () {
    $comprobante = comprobanteDePrueba();

    $comprobante->marcarProcesando();
    $comprobante->marcarRechazado();

    expect(fn () => $comprobante->reintentar())
        ->toThrow(TransicionEstadoInvalidaException::class);
});

it('nunca permite saltar directamente a ACEPTADO sin pasar por PROCESANDO', function () {
    $comprobante = comprobanteDePrueba();

    expect(fn () => $comprobante->marcarAceptado())
        ->toThrow(TransicionEstadoInvalidaException::class);
});

it('distingue ACEPTADO_CON_OBSERVACIONES de ACEPTADO y de RECHAZADO', function () {
    $comprobante = comprobanteDePrueba();

    $comprobante->marcarProcesando();
    $comprobante->marcarAceptadoConObservaciones();

    expect($comprobante->estado())->toBe(EstadoComprobante::AceptadoConObservaciones)
        ->and($comprobante->estado())->not->toBe(EstadoComprobante::Aceptado)
        ->and($comprobante->estado())->not->toBe(EstadoComprobante::Rechazado);
});
