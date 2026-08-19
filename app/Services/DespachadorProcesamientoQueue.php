<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\ProcesarComprobante;
use Modules\Facturacion\Domain\Puertos\DespachadorProcesamiento;

final class DespachadorProcesamientoQueue implements DespachadorProcesamiento
{
    public function despacharEnvio(string $empresaId, string $comprobanteId, ?string $requestId = null): void
    {
        $entorno = (string) config('facturacion.sunat.entorno_por_defecto');

        ProcesarComprobante::dispatch($empresaId, $comprobanteId, strtoupper($entorno), $requestId);
    }
}
