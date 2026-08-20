<?php

declare(strict_types=1);

use App\Jobs\ProcesarComprobante;
use Illuminate\Support\Facades\Log;
use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\DespachadorWebhooks;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;

afterEach(function () {
    Log::flushSharedContext();
    Log::withoutContext();
});

it('comparte la trazabilidad HTTP con los logs del worker', function () {
    $repositorio = Mockery::mock(RepositorioComprobante::class);
    $repositorio->shouldReceive('buscarPorId')
        ->once()
        ->with('empresa-1', 'comprobante-1')
        ->andReturnNull();

    $casoDeUso = new ProcesarEnvioComprobante(
        $repositorio,
        Mockery::mock(ProveedorDatosSunat::class),
        Mockery::mock(GeneradorXmlFirmado::class),
        Mockery::mock(FabricaEnviadorComprobante::class),
        Mockery::mock(AlmacenPrivado::class),
        Mockery::mock(RegistradorTrazabilidadComprobante::class),
        Mockery::mock(DespachadorWebhooks::class),
    );

    $job = new ProcesarComprobante(
        empresaId: 'empresa-1',
        comprobanteId: 'comprobante-1',
        entorno: 'BETA',
        requestId: 'req-9841',
    );

    expect($job->uniqueId())->toBe('empresa-1:comprobante-1');

    expect(fn () => $job->handle($casoDeUso))
        ->toThrow(ComprobanteInvalidoException::class);

    expect(Log::sharedContext())->toMatchArray([
        'request_id' => 'req-9841',
        'empresa_id' => 'empresa-1',
        'comprobante_id' => 'comprobante-1',
    ]);
});
