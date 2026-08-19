<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Certificados;

final class CertificadoDigitalPreparado
{
    public function __construct(
        public readonly string $contenidoPem,
        public readonly DatosCertificadoAnalizado $datos,
    ) {}
}
