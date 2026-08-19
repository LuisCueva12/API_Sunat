<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;

final class ProcesarComprobante implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $empresaId,
        public readonly string $comprobanteId,
        public readonly string $entorno,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(ProcesarEnvioComprobante $procesarEnvioComprobante): void
    {
        $procesarEnvioComprobante->ejecutar($this->empresaId, $this->comprobanteId, $this->entorno);
    }
}
