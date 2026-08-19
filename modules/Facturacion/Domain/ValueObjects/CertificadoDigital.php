<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

final class CertificadoDigital
{
    public function __construct(
        public readonly string $contenidoPem,
    ) {
        if (trim($contenidoPem) === '') {
            throw new InvalidArgumentException('El contenido del certificado no puede estar vacío.');
        }
    }
}
