<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\CrearIntegracionApi;
use Modules\Facturacion\Application\DTO\CrearIntegracionApiInput;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\IntegracionApi;
use Modules\Facturacion\Domain\Empresa\ResultadoClienteOAuth;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Excepciones\IntegracionApiInvalidaException;
use Modules\Facturacion\Domain\Puertos\GestorClientesOAuth;
use Modules\Facturacion\Domain\Puertos\RepositorioIntegracionApi;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

function repositorioIntegracionApiFalso(): RepositorioIntegracionApi
{
    return new class implements RepositorioIntegracionApi
    {
        /** @var array<string, IntegracionApi> */
        public array $guardados = [];

        public function guardar(IntegracionApi $integracion): void
        {
            $this->guardados[$integracion->id()] = $integracion;
        }

        public function buscarPorId(string $empresaId, string $id): ?IntegracionApi
        {
            $integracion = $this->guardados[$id] ?? null;

            return $integracion?->empresaId() === $empresaId ? $integracion : null;
        }
    };
}

function gestorClientesOAuthFalso(): GestorClientesOAuth
{
    return new class implements GestorClientesOAuth
    {
        public bool $revocado = false;

        public function crear(string $empresaId, string $nombre, array $scopes): ResultadoClienteOAuth
        {
            return new ResultadoClienteOAuth('oauth-client-de-prueba', 'secreto-de-prueba');
        }

        public function revocar(string $oauthClientId): void
        {
            $this->revocado = true;
        }
    };
}

it('crea una integración y devuelve el client_secret en texto plano una sola vez', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorio = repositorioIntegracionApiFalso();

    $casoDeUso = new CrearIntegracionApi(repositorioEmpresaFalso($empresa), $repositorio, gestorClientesOAuthFalso());

    $resultado = $casoDeUso->ejecutar(new CrearIntegracionApiInput('empresa-1', 'Integración principal', ['comprobantes:crear']));

    expect($resultado->clientSecret)->toBe('secreto-de-prueba')
        ->and($resultado->integracion->id())->toBe('oauth-client-de-prueba')
        ->and($resultado->integracion->estaVigente())->toBeTrue()
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rechaza crear una integración con un scope inválido', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $casoDeUso = new CrearIntegracionApi(repositorioEmpresaFalso($empresa), repositorioIntegracionApiFalso(), gestorClientesOAuthFalso());

    $casoDeUso->ejecutar(new CrearIntegracionApiInput('empresa-1', 'Nombre', ['admin:todo']));
})->throws(IntegracionApiInvalidaException::class);

it('rechaza crear una integración para una empresa inexistente', function () {
    $casoDeUso = new CrearIntegracionApi(repositorioEmpresaFalso(null), repositorioIntegracionApiFalso(), gestorClientesOAuthFalso());

    $casoDeUso->ejecutar(new CrearIntegracionApiInput('empresa-1', 'Nombre', ['comprobantes:leer']));
})->throws(EmpresaInvalidaException::class);
