<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteNoEncontradoException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Modules\Facturacion\Domain\Puertos\DespachadorProcesamiento;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function comprobanteConEstadoError(): Comprobante
{
    $comprobante = Comprobante::registrar(
        id: 'comprobante-1',
        empresaId: 'empresa-1',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, '20100070970'),
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-19'),
    );

    $comprobante->marcarProcesando();
    $comprobante->marcarError('SUNAT no disponible');

    return $comprobante;
}

function repositorioComprobanteParaReintento(?Comprobante $comprobante): RepositorioComprobante
{
    return new class($comprobante) implements RepositorioComprobante
    {
        public function __construct(private readonly ?Comprobante $comprobante) {}

        public function guardar(Comprobante $comprobante): void {}

        public function buscarPorId(string $empresaId, string $id): ?Comprobante
        {
            return $this->comprobante;
        }

        public function actualizarEstado(
            Comprobante $comprobante,
            ?string $xmlSha256 = null,
            ?string $cdrSha256 = null,
        ): void {}
    };
}

function despachadorParaReintento(): DespachadorProcesamiento
{
    return new class implements DespachadorProcesamiento
    {
        /** @var array<int, array{string, string, ?string}> */
        public array $envios = [];

        public function despacharEnvio(string $empresaId, string $comprobanteId, ?string $requestId = null): void
        {
            $this->envios[] = [$empresaId, $comprobanteId, $requestId];
        }
    };
}

function trazabilidadParaReintento(): RegistradorTrazabilidadComprobante
{
    return new class implements RegistradorTrazabilidadComprobante
    {
        public array $eventos = [];

        public function registrarEvento(
            Comprobante $comprobante,
            string $tipo,
            ?string $actor = null,
            ?string $requestId = null,
            array $datos = [],
        ): void {
            $this->eventos[] = [$tipo, $actor, $requestId, $datos];
        }

        public function registrarEnvio(
            Comprobante $comprobante,
            string $entorno,
            int $intento,
            ?ResultadoEnvio $resultado,
            ?string $rutaXml,
            ?string $rutaCdr,
            int $duracionMs,
            ?string $errorTecnico = null,
        ): void {}
    };
}

it('programa nuevamente un comprobante en estado error', function () {
    $despachador = despachadorParaReintento();
    $casoDeUso = new ReintentarComprobante(
        repositorioComprobanteParaReintento(comprobanteConEstadoError()),
        $despachador,
        trazabilidadParaReintento(),
    );

    $comprobante = $casoDeUso->ejecutar('empresa-1', 'comprobante-1', 'req-9841');

    expect($comprobante->esReintentable())->toBeTrue()
        ->and($despachador->envios)->toBe([
            ['empresa-1', 'comprobante-1', 'req-9841'],
        ]);
});

it('rechaza reintentar si el comprobante no está en error', function () {
    $despachador = despachadorParaReintento();
    $comprobante = comprobanteConEstadoError();
    $comprobante->reintentar();

    $casoDeUso = new ReintentarComprobante(
        repositorioComprobanteParaReintento($comprobante),
        $despachador,
        trazabilidadParaReintento(),
    );

    expect(fn () => $casoDeUso->ejecutar('empresa-1', 'comprobante-1'))
        ->toThrow(TransicionEstadoInvalidaException::class)
        ->and($despachador->envios)->toBeEmpty();
});

it('no revela comprobantes inexistentes o de otra empresa', function () {
    $casoDeUso = new ReintentarComprobante(
        repositorioComprobanteParaReintento(null),
        despachadorParaReintento(),
        trazabilidadParaReintento(),
    );

    expect(fn () => $casoDeUso->ejecutar('empresa-2', 'comprobante-1'))
        ->toThrow(ComprobanteNoEncontradoException::class);
});
