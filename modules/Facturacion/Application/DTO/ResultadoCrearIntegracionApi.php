<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

use Modules\Facturacion\Domain\Empresa\IntegracionApi;

final class ResultadoCrearIntegracionApi
{
    public function __construct(
        public readonly IntegracionApi $integracion,
        public readonly string $clientSecret,
    ) {}
}
