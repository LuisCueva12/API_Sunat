<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Tributario\CalculadorTributos;
use Modules\Facturacion\Domain\ValueObjects\Dinero;

it('calcula el IGV de un item gravado al 18%', function () {
    $calculador = new CalculadorTributos;

    $item = $calculador->calcularItem(
        numeroOrden: 1,
        descripcion: 'Servicio de prueba',
        unidadMedida: 'NIU',
        cantidad: 2.0,
        valorUnitario: Dinero::desde('50.00'),
        tipoAfectacionIgv: '10',
    );

    expect($item->montoValorVenta()->comoString())->toBe('100.00')
        ->and($item->montoIgv()->comoString())->toBe('18.00')
        ->and($item->precioUnitario()->comoString())->toBe('59.00');
});

it('resta el descuento antes de calcular el IGV', function () {
    $calculador = new CalculadorTributos;

    $item = $calculador->calcularItem(
        numeroOrden: 1,
        descripcion: 'Servicio con descuento',
        unidadMedida: 'NIU',
        cantidad: 1.0,
        valorUnitario: Dinero::desde('100.00'),
        tipoAfectacionIgv: '10',
        descuento: Dinero::desde('10.00'),
    );

    expect($item->montoValorVenta()->comoString())->toBe('90.00')
        ->and($item->montoIgv()->comoString())->toBe('16.20');
});

it('rechaza tipos de afectación IGV no soportados en V1', function () {
    $calculador = new CalculadorTributos;

    $calculador->calcularItem(
        numeroOrden: 1,
        descripcion: 'Servicio exonerado',
        unidadMedida: 'NIU',
        cantidad: 1.0,
        valorUnitario: Dinero::desde('100.00'),
        tipoAfectacionIgv: '20', // exonerado — no soportado todavía
    );
})->throws(ComprobanteInvalidoException::class);

it('agrega los totales de varios items y genera el tributo IGV', function () {
    $calculador = new CalculadorTributos;

    $items = [
        $calculador->calcularItem(1, 'Item 1', 'NIU', 1.0, Dinero::desde('100.00'), '10'),
        $calculador->calcularItem(2, 'Item 2', 'NIU', 2.0, Dinero::desde('25.00'), '10'),
    ];

    $resultado = $calculador->calcularTotales($items);

    expect($resultado['totales']->opGravada->comoString())->toBe('150.00')
        ->and($resultado['totales']->totalIgv->comoString())->toBe('27.00')
        ->and($resultado['totales']->total->comoString())->toBe('177.00')
        ->and($resultado['tributos'])->toHaveCount(1)
        ->and($resultado['tributos'][0]->tipoTributo())->toBe('IGV');
});
