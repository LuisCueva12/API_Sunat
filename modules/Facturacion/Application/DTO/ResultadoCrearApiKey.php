<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;

final class ResultadoCrearApiKey
{
    public function __construct(
        public readonly ApiKeyEmpresa $apiKey,
        public readonly string $claveCompleta,
    ) {}
}
