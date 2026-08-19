<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

final class ResultadoClienteOAuth
{
    public function __construct(
        public readonly string $clientId,
        public readonly string $clientSecret,
    ) {}
}
