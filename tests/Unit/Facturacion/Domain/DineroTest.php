<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\ValueObjects\Dinero;

it('parsea un monto decimal a centavos sin perder precisión', function () {
    expect(Dinero::desde('125.50')->centavosComoEntero())->toBe(12550)
        ->and(Dinero::desde('100')->centavosComoEntero())->toBe(10000)
        ->and(Dinero::desde('0.01')->centavosComoEntero())->toBe(1);
});

it('rechaza montos con formato inválido', function () {
    Dinero::desde('12.5.0');
})->throws(InvalidArgumentException::class);

it('suma y resta preservando exactitud decimal', function () {
    $a = Dinero::desde('10.10');
    $b = Dinero::desde('0.20');

    expect($a->sumar($b)->comoString())->toBe('10.30')
        ->and($a->restar($b)->comoString())->toBe('9.90');
});

it('calcula IGV con redondeo half-up', function () {
    $valorVenta = Dinero::desde('100.00');

    expect($valorVenta->multiplicarPor(0.18)->comoString())->toBe('18.00');
});

it('nunca pierde el signo en operaciones negativas', function () {
    $descuento = Dinero::desde('50.00')->restar(Dinero::desde('80.00'));

    expect($descuento->esNegativo())->toBeTrue()
        ->and($descuento->comoString())->toBe('-30.00');
});
