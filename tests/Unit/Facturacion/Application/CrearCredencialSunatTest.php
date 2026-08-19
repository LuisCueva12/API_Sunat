<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\CrearCredencialSunat;
use Modules\Facturacion\Application\DTO\CrearCredencialSunatInput;
use Modules\Facturacion\Domain\Empresa\CredencialSunatEmpresa;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\EntornoSunat;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\RepositorioCredencialSunat;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

function repositorioCredencialSunatFalso(?CredencialSunatEmpresa $existente = null): RepositorioCredencialSunat
{
    return new class($existente) implements RepositorioCredencialSunat
    {
        public array $guardados = [];

        public function __construct(private readonly ?CredencialSunatEmpresa $existente) {}

        public function guardar(CredencialSunatEmpresa $credencial): void
        {
            $this->guardados[] = $credencial;
        }

        public function buscarPorEmpresaYEntorno(string $empresaId, EntornoSunat $entorno): ?CredencialSunatEmpresa
        {
            return $this->existente;
        }
    };
}

it('registra una credencial nueva para una empresa activa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorio = repositorioCredencialSunatFalso();

    $casoDeUso = new CrearCredencialSunat($repositorio, repositorioEmpresaFalso($empresa), generadorIdFalso());

    $credencial = $casoDeUso->ejecutar(new CrearCredencialSunatInput('empresa-1', 'BETA', 'MODDATOS', 'moddatos'));

    expect($credencial->entorno())->toBe(EntornoSunat::Beta)
        ->and($credencial->estaActiva())->toBeTrue()
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rota una credencial existente para el mismo entorno en lugar de duplicarla', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $existente = CredencialSunatEmpresa::reconstituir(
        id: 'credencial-1',
        empresaId: 'empresa-1',
        entorno: EntornoSunat::Beta,
        usuarioSol: 'VIEJO',
        claveSol: 'viejaclave',
        activa: true,
    );

    $repositorio = repositorioCredencialSunatFalso($existente);

    $casoDeUso = new CrearCredencialSunat($repositorio, repositorioEmpresaFalso($empresa), generadorIdFalso());

    $credencial = $casoDeUso->ejecutar(new CrearCredencialSunatInput('empresa-1', 'BETA', 'NUEVO', 'nuevaclave'));

    expect($credencial->id())->toBe('credencial-1')
        ->and($credencial->usuarioSol())->toBe('NUEVO')
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rechaza un entorno SUNAT desconocido', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $casoDeUso = new CrearCredencialSunat(repositorioCredencialSunatFalso(), repositorioEmpresaFalso($empresa), generadorIdFalso());

    $casoDeUso->ejecutar(new CrearCredencialSunatInput('empresa-1', 'STAGING', 'usuario', 'clave'));
})->throws(ValueError::class);

it('rechaza registrar una credencial para una empresa inexistente', function () {
    $casoDeUso = new CrearCredencialSunat(repositorioCredencialSunatFalso(), repositorioEmpresaFalso(null), generadorIdFalso());

    $casoDeUso->ejecutar(new CrearCredencialSunatInput('empresa-1', 'BETA', 'usuario', 'clave'));
})->throws(EmpresaInvalidaException::class);
