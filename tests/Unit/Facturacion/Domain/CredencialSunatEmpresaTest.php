<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Empresa\CredencialSunatEmpresa;
use Modules\Facturacion\Domain\Empresa\EntornoSunat;
use Modules\Facturacion\Domain\Excepciones\CredencialSunatInvalidaException;

it('se registra activa', function () {
    $credencial = CredencialSunatEmpresa::registrar(
        id: 'credencial-1',
        empresaId: 'empresa-1',
        entorno: EntornoSunat::Beta,
        usuarioSol: 'MODDATOS',
        claveSol: 'moddatos',
    );

    expect($credencial->estaActiva())->toBeTrue()
        ->and($credencial->entorno())->toBe(EntornoSunat::Beta);
});

it('rechaza un usuario SOL vacío', function () {
    CredencialSunatEmpresa::registrar('credencial-1', 'empresa-1', EntornoSunat::Beta, '   ', 'clave');
})->throws(CredencialSunatInvalidaException::class);

it('rechaza una clave SOL vacía', function () {
    CredencialSunatEmpresa::registrar('credencial-1', 'empresa-1', EntornoSunat::Beta, 'usuario', '');
})->throws(CredencialSunatInvalidaException::class);

it('puede rotarse y reactivarse', function () {
    $credencial = CredencialSunatEmpresa::reconstituir(
        id: 'credencial-1',
        empresaId: 'empresa-1',
        entorno: EntornoSunat::Produccion,
        usuarioSol: 'VIEJO',
        claveSol: 'viejaclave',
        activa: false,
    );

    $credencial->rotar('NUEVO', 'nuevaclave');

    expect($credencial->usuarioSol())->toBe('NUEVO')
        ->and($credencial->claveSol())->toBe('nuevaclave')
        ->and($credencial->estaActiva())->toBeTrue();
});
