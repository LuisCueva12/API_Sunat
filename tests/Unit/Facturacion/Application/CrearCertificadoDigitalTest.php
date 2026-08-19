<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\CrearCertificadoDigital;
use Modules\Facturacion\Application\DTO\CrearCertificadoDigitalInput;
use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;
use Modules\Facturacion\Domain\Empresa\CertificadoEmpresa;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;
use Modules\Facturacion\Domain\Puertos\RepositorioCertificado;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

function repositorioCertificadoFalso(?CertificadoEmpresa $activoExistente = null): RepositorioCertificado
{
    return new class($activoExistente) implements RepositorioCertificado
    {
        public array $guardados = [];

        public function __construct(private readonly ?CertificadoEmpresa $activoExistente) {}

        public function guardar(CertificadoEmpresa $certificado): void
        {
            $this->guardados[] = $certificado;
        }

        public function buscarActivoPorEmpresa(string $empresaId): ?CertificadoEmpresa
        {
            return $this->activoExistente;
        }
    };
}

function transaccionesFalso(): GestorTransacciones
{
    return new class implements GestorTransacciones
    {
        public function ejecutar(Closure $operacion): mixed
        {
            return $operacion();
        }
    };
}

it('registra un certificado activo para una empresa activa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorio = repositorioCertificadoFalso();

    $casoDeUso = new CrearCertificadoDigital(
        $repositorio,
        repositorioEmpresaFalso($empresa),
        generadorIdFalso(),
        new AnalizadorCertificadoDigital,
        transaccionesFalso(),
    );

    $certificado = $casoDeUso->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: 'empresa-1',
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'secreto',
        alias: 'Principal',
    ));

    expect($certificado->empresaId())->toBe('empresa-1')
        ->and($certificado->estaVigente())->toBeTrue()
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('reemplaza el certificado activo previo al registrar uno nuevo', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $activoPrevio = CertificadoEmpresa::registrar(
        id: 'certificado-viejo',
        empresaId: 'empresa-1',
        alias: null,
        contenidoPem: '-----BEGIN CERTIFICATE-----...',
        passwordCertificado: 'x',
        huellaSha256: str_repeat('B', 64),
        fechaEmision: null,
        fechaExpiracion: new DateTimeImmutable('+1 year'),
    );

    $repositorio = repositorioCertificadoFalso($activoPrevio);

    $casoDeUso = new CrearCertificadoDigital(
        $repositorio,
        repositorioEmpresaFalso($empresa),
        generadorIdFalso(),
        new AnalizadorCertificadoDigital,
        transaccionesFalso(),
    );

    $casoDeUso->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: 'empresa-1',
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'secreto',
    ));

    expect($repositorio->guardados)->toHaveCount(2)
        ->and($activoPrevio->estaVigente())->toBeFalse();
});

it('rechaza registrar un certificado para una empresa inexistente', function () {
    $casoDeUso = new CrearCertificadoDigital(
        repositorioCertificadoFalso(),
        repositorioEmpresaFalso(null),
        generadorIdFalso(),
        new AnalizadorCertificadoDigital,
        transaccionesFalso(),
    );

    $casoDeUso->ejecutar(new CrearCertificadoDigitalInput('empresa-1', generarCertificadoPemDePrueba(), 'secreto'));
})->throws(EmpresaInvalidaException::class);
