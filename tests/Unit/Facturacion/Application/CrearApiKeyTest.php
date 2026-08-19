<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\CrearApiKey;
use Modules\Facturacion\Application\DTO\CrearApiKeyInput;
use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\ResultadoClaveApi;
use Modules\Facturacion\Domain\Excepciones\ApiKeyInvalidaException;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorClaveApi;
use Modules\Facturacion\Domain\Puertos\RepositorioApiKey;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

function repositorioApiKeyFalso(): RepositorioApiKey
{
    return new class implements RepositorioApiKey
    {
        public array $guardados = [];

        public function guardar(ApiKeyEmpresa $apiKey): void
        {
            $this->guardados[] = $apiKey;
        }
    };
}

function generadorClaveApiFalso(): GeneradorClaveApi
{
    return new class implements GeneradorClaveApi
    {
        public function generar(): ResultadoClaveApi
        {
            return new ResultadoClaveApi('fe_live_claveDePrueba', 'fe_live_claveDe', 'hash-de-prueba');
        }

        public function hash(string $claveCompleta): string
        {
            return 'hash-de-prueba';
        }
    };
}

it('genera y persiste una API Key devolviendo la clave en texto plano una sola vez', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorio = repositorioApiKeyFalso();

    $casoDeUso = new CrearApiKey($repositorio, repositorioEmpresaFalso($empresa), generadorIdFalso(), generadorClaveApiFalso());

    $resultado = $casoDeUso->ejecutar(new CrearApiKeyInput('empresa-1', 'Integración principal', ['comprobantes:crear']));

    expect($resultado->claveCompleta)->toBe('fe_live_claveDePrueba')
        ->and($resultado->apiKey->hash())->toBe('hash-de-prueba')
        ->and($resultado->apiKey->estaVigente())->toBeTrue()
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rechaza crear una API Key con un scope inválido', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $casoDeUso = new CrearApiKey(repositorioApiKeyFalso(), repositorioEmpresaFalso($empresa), generadorIdFalso(), generadorClaveApiFalso());

    $casoDeUso->ejecutar(new CrearApiKeyInput('empresa-1', 'Nombre', ['admin:todo']));
})->throws(ApiKeyInvalidaException::class);

it('rechaza crear una API Key para una empresa inexistente', function () {
    $casoDeUso = new CrearApiKey(repositorioApiKeyFalso(), repositorioEmpresaFalso(null), generadorIdFalso(), generadorClaveApiFalso());

    $casoDeUso->ejecutar(new CrearApiKeyInput('empresa-1', 'Nombre', ['comprobantes:leer']));
})->throws(EmpresaInvalidaException::class);
