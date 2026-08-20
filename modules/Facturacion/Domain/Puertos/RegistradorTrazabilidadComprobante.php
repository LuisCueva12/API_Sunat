<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;

interface RegistradorTrazabilidadComprobante
{
    /** @param array<string, mixed> $datos */
    public function registrarEvento(
        Comprobante $comprobante,
        string $tipo,
        ?string $actor = null,
        ?string $requestId = null,
        array $datos = [],
    ): void;

    public function registrarEnvio(
        Comprobante $comprobante,
        string $entorno,
        int $intento,
        ?ResultadoEnvio $resultado,
        ?string $rutaXml,
        ?string $rutaCdr,
        int $duracionMs,
        ?string $errorTecnico = null,
    ): void;
}
