<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;

final class ProcesarComprobante implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public readonly string $empresaId,
        public readonly string $comprobanteId,
        public readonly string $entorno,
        public readonly ?string $requestId = null,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function handle(ProcesarEnvioComprobante $procesarEnvioComprobante): void
    {
        Log::shareContext(array_filter([
            'request_id' => $this->requestId,
            'empresa_id' => $this->empresaId,
            'comprobante_id' => $this->comprobanteId,
        ], static fn (?string $valor): bool => $valor !== null && $valor !== ''));

        $procesarEnvioComprobante->ejecutar($this->empresaId, $this->comprobanteId, $this->entorno);
    }
}
