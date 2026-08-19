<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class CrearSerieInput
{
    public function __construct(
        public readonly string $empresaId,
        public readonly string $tipoComprobante,
        public readonly string $serie,
    ) {}
}
