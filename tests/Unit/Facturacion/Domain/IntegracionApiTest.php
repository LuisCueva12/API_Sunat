<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Empresa\EstadoIntegracionApi;
use Modules\Facturacion\Domain\Empresa\IntegracionApi;
use Modules\Facturacion\Domain\Excepciones\IntegracionApiInvalidaException;

it('se registra activa y vigente', function () {
    $integracion = IntegracionApi::registrar(
        id: 'oauth-client-1',
        empresaId: 'empresa-1',
        nombre: 'Integración principal',
        scopes: ['comprobantes:crear'],
    );

    expect($integracion->estado())->toBe(EstadoIntegracionApi::Activa)
        ->and($integracion->estaVigente())->toBeTrue();
});

it('rechaza un nombre vacío', function () {
    IntegracionApi::registrar('oauth-client-1', 'empresa-1', '   ', ['comprobantes:crear']);
})->throws(IntegracionApiInvalidaException::class);

it('rechaza registrarse sin scopes', function () {
    IntegracionApi::registrar('oauth-client-1', 'empresa-1', 'Nombre', []);
})->throws(IntegracionApiInvalidaException::class);

it('rechaza un scope desconocido', function () {
    IntegracionApi::registrar('oauth-client-1', 'empresa-1', 'Nombre', ['comprobantes:eliminar']);
})->throws(IntegracionApiInvalidaException::class);

it('permite el scope para reintentar comprobantes con error', function () {
    $integracion = IntegracionApi::registrar('oauth-client-1', 'empresa-1', 'Operación de reintentos', ['comprobantes:reintentar']);

    expect($integracion->scopes())->toContain('comprobantes:reintentar');
});

it('no está vigente si fue revocada', function () {
    $integracion = IntegracionApi::registrar('oauth-client-1', 'empresa-1', 'Nombre', ['comprobantes:leer']);

    $integracion->revocar();

    expect($integracion->estaVigente())->toBeFalse()
        ->and($integracion->estado())->toBe(EstadoIntegracionApi::Revocada);
});
