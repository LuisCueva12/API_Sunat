<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

/**
 * Encola el procesamiento asíncrono de un comprobante ya persistido.
 * Existe como puerto (en vez de que Application llame Bus::dispatch()
 * directo) porque Application no depende de Illuminate — mismo criterio
 * que GestorTransacciones.
 */
interface DespachadorProcesamiento
{
    // Sin parámetro $entorno a propósito: BETA/PRODUCCION es una decisión
    // de despliegue/config, no un dato de negocio — Application no debe
    // conocerlo. El adaptador lo resuelve (ver DespachadorProcesamientoQueue).
    public function despacharEnvio(string $empresaId, string $comprobanteId): void;
}
