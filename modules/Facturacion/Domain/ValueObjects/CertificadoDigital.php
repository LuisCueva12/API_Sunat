<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Portador transitorio del contenido ya descifrado de un certificado
 * (PEM: clave privada + certificado). Vive solo en memoria durante el
 * pipeline de firma — nunca se serializa a log ni se persiste tal cual.
 */
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
