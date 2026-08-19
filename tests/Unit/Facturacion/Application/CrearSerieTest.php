<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\CrearSerie;
use Modules\Facturacion\Application\DTO\CrearSerieInput;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Empresa\SerieEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioSerie;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;

function repositorioEmpresaFalso(?Empresa $empresa): RepositorioEmpresa
{
    return new class($empresa) implements RepositorioEmpresa
    {
        public function __construct(private readonly ?Empresa $empresa) {}

        public function guardar(Empresa $empresa): void {}

        public function buscarPorId(string $id): ?Empresa
        {
            return $this->empresa;
        }

        public function buscarPorRuc(string $ruc): ?Empresa
        {
            return $this->empresa;
        }
    };
}

function repositorioSerieFalso(bool $existe = false): RepositorioSerie
{
    return new class($existe) implements RepositorioSerie
    {
        public array $guardadas = [];

        public function __construct(private readonly bool $existe) {}

        public function guardar(SerieEmpresa $serie): void
        {
            $this->guardadas[] = $serie;
        }

        public function existe(string $empresaId, TipoComprobante $tipo, Serie $serie): bool
        {
            return $this->existe;
        }
    };
}

function generadorIdFalso(): GeneradorId
{
    return new class implements GeneradorId
    {
        public function nuevo(): string
        {
            return 'serie-1';
        }
    };
}

it('crea una serie para una empresa activa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorioSerie = repositorioSerieFalso(existe: false);

    $casoDeUso = new CrearSerie(
        $repositorioSerie,
        repositorioEmpresaFalso($empresa),
        generadorIdFalso(),
    );

    $serie = $casoDeUso->ejecutar(new CrearSerieInput('empresa-1', 'FACTURA', 'F001'));

    expect($serie->id())->toBe('serie-1')
        ->and($serie->empresaId())->toBe('empresa-1')
        ->and($serie->tipoComprobante())->toBe(TipoComprobante::Factura)
        ->and($serie->serie()->valor())->toBe('F001')
        ->and($serie->estaActiva())->toBeTrue()
        ->and($repositorioSerie->guardadas)->toHaveCount(1);
});

it('rechaza crear una serie si la empresa no existe', function () {
    $casoDeUso = new CrearSerie(
        repositorioSerieFalso(),
        repositorioEmpresaFalso(null),
        generadorIdFalso(),
    );

    $casoDeUso->ejecutar(new CrearSerieInput('empresa-1', 'FACTURA', 'F001'));
})->throws(EmpresaInvalidaException::class);

it('rechaza crear una serie si la empresa no está activa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $empresa->desactivar();

    $casoDeUso = new CrearSerie(
        repositorioSerieFalso(),
        repositorioEmpresaFalso($empresa),
        generadorIdFalso(),
    );

    $casoDeUso->ejecutar(new CrearSerieInput('empresa-1', 'FACTURA', 'F001'));
})->throws(EmpresaInvalidaException::class);

it('rechaza crear una serie duplicada para el mismo tipo', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');

    $casoDeUso = new CrearSerie(
        repositorioSerieFalso(existe: true),
        repositorioEmpresaFalso($empresa),
        generadorIdFalso(),
    );

    $casoDeUso->ejecutar(new CrearSerieInput('empresa-1', 'FACTURA', 'F001'));
})->throws(SerieInvalidaException::class);
