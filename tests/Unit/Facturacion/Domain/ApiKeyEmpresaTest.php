<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;
use Modules\Facturacion\Domain\Empresa\EstadoApiKey;
use Modules\Facturacion\Domain\Excepciones\ApiKeyInvalidaException;

it('se registra activa y vigente sin expiración', function () {
    $apiKey = ApiKeyEmpresa::registrar(
        id: 'apikey-1',
        empresaId: 'empresa-1',
        nombre: 'Integración principal',
        prefijo: 'fe_live_abc123',
        hash: str_repeat('a', 64),
        scopes: ['comprobantes:crear'],
        expiraEn: null,
    );

    expect($apiKey->estado())->toBe(EstadoApiKey::Activa)
        ->and($apiKey->estaVigente())->toBeTrue();
});

it('rechaza un nombre vacío', function () {
    ApiKeyEmpresa::registrar('apikey-1', 'empresa-1', '   ', 'prefijo', 'hash', ['comprobantes:crear'], null);
})->throws(ApiKeyInvalidaException::class);

it('rechaza registrarse sin scopes', function () {
    ApiKeyEmpresa::registrar('apikey-1', 'empresa-1', 'Nombre', 'prefijo', 'hash', [], null);
})->throws(ApiKeyInvalidaException::class);

it('rechaza un scope desconocido', function () {
    ApiKeyEmpresa::registrar('apikey-1', 'empresa-1', 'Nombre', 'prefijo', 'hash', ['comprobantes:eliminar'], null);
})->throws(ApiKeyInvalidaException::class);

it('no está vigente si ya expiró', function () {
    $apiKey = ApiKeyEmpresa::reconstituir(
        id: 'apikey-1',
        empresaId: 'empresa-1',
        nombre: 'Nombre',
        prefijo: 'prefijo',
        hash: 'hash',
        scopes: ['comprobantes:leer'],
        expiraEn: new DateTimeImmutable('-1 day'),
        estado: EstadoApiKey::Activa,
    );

    expect($apiKey->estaVigente())->toBeFalse();
});

it('no está vigente si fue revocada', function () {
    $apiKey = ApiKeyEmpresa::registrar('apikey-1', 'empresa-1', 'Nombre', 'prefijo', 'hash', ['comprobantes:leer'], null);

    $apiKey->revocar();

    expect($apiKey->estaVigente())->toBeFalse();
});
