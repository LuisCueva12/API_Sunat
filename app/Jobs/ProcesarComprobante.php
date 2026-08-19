<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;

final class ProcesarComprobante implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly string $empresaId,
        public readonly string $comprobanteId,
        public readonly string $entorno,
        public readonly ?string $requestId = null,
    ) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60, 300];
    }

    public function uniqueId(): string
    {
        return "{$this->empresaId}:{$this->comprobanteId}";
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
