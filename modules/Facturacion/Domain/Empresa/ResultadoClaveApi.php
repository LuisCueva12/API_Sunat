<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

final class ResultadoClaveApi
{
    public function __construct(
        public readonly string $claveCompleta,
        public readonly string $prefijo,
        public readonly string $hash,
    ) {}
}
