<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\EstadoEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

it('se registra activa por defecto', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa de Prueba SAC');

    expect($empresa->estado())->toBe(EstadoEmpresa::Activa)
        ->and($empresa->estaActiva())->toBeTrue()
        ->and($empresa->ruc()->valor())->toBe('20100070970');
});

it('rechaza una razón social vacía', function () {
    Empresa::registrar('empresa-1', new Ruc('20100070970'), '   ');
})->throws(EmpresaInvalidaException::class);

it('puede desactivarse y reactivarse', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $empresa->desactivar();
    expect($empresa->estaActiva())->toBeFalse()
        ->and($empresa->estado())->toBe(EstadoEmpresa::Inactiva);

    $empresa->activar();
    expect($empresa->estaActiva())->toBeTrue();
});

it('puede suspenderse', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $empresa->suspender();

    expect($empresa->estado())->toBe(EstadoEmpresa::Suspendida)
        ->and($empresa->estaActiva())->toBeFalse();
});
