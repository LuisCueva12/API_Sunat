<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\ValueObjects\Ruc;

final class DatosEmisor
{
    public function __construct(
        public readonly Ruc $ruc,
        public readonly string $razonSocial,
        public readonly ?string $nombreComercial,
        public readonly ?string $direccion,
        public readonly ?string $ubigeo,
    ) {}
}
