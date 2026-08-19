<?php

declare(strict_types=1);

use App\Jobs\ProcesarComprobante;
use App\Services\DespachadorProcesamientoQueue;
use Illuminate\Support\Facades\Bus;

it('incluye el request id al encolar el comprobante', function () {
    Bus::fake();
    config()->set('facturacion.sunat.entorno_por_defecto', 'beta');

    (new DespachadorProcesamientoQueue)->despacharEnvio(
        'empresa-1',
        'comprobante-1',
        'req-9841',
    );

    Bus::assertDispatched(
        ProcesarComprobante::class,
        fn (ProcesarComprobante $job): bool => $job->empresaId === 'empresa-1'
            && $job->comprobanteId === 'comprobante-1'
            && $job->entorno === 'BETA'
            && $job->requestId === 'req-9841',
    );
});
