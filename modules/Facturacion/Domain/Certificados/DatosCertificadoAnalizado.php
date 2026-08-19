<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Certificados;

use DateTimeImmutable;

final class DatosCertificadoAnalizado
{
    public function __construct(
        public readonly string $huellaSha256,
        public readonly ?DateTimeImmutable $fechaEmision,
        public readonly DateTimeImmutable $fechaExpiracion,
    ) {}
}
