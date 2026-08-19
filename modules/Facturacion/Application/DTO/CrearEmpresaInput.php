<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class CrearEmpresaInput
{
    public function __construct(
        public readonly string $ruc,
        public readonly string $razonSocial,
        public readonly ?string $nombreComercial = null,
    ) {}
}
