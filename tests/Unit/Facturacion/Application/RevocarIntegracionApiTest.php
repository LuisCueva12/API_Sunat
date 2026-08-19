<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\RevocarIntegracionApi;
use Modules\Facturacion\Domain\Empresa\EstadoIntegracionApi;
use Modules\Facturacion\Domain\Empresa\IntegracionApi;
use Modules\Facturacion\Domain\Excepciones\IntegracionApiInvalidaException;

it('revoca una integración existente', function () {
    $repositorio = repositorioIntegracionApiFalso();
    $repositorio->guardar(IntegracionApi::registrar('oauth-client-1', 'empresa-1', 'Integración', ['comprobantes:crear']));
    $gestor = gestorClientesOAuthFalso();

    $casoDeUso = new RevocarIntegracionApi($repositorio, $gestor);
    $casoDeUso->ejecutar('empresa-1', 'oauth-client-1');

    expect($gestor->revocado)->toBeTrue()
        ->and($repositorio->buscarPorId('empresa-1', 'oauth-client-1')?->estado())->toBe(EstadoIntegracionApi::Revocada);
});

it('rechaza revocar una integración que no existe o no pertenece a la empresa', function () {
    $casoDeUso = new RevocarIntegracionApi(repositorioIntegracionApiFalso(), gestorClientesOAuthFalso());

    $casoDeUso->ejecutar('empresa-1', 'oauth-client-no-existe');
})->throws(IntegracionApiInvalidaException::class);
