<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class CrearCertificadoDigitalInput
{
    public function __construct(
        public readonly string $empresaId,
        public readonly string $contenidoPem,
        public readonly string $password,
        public readonly ?string $alias = null,
    ) {}
}
