<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;

final class DatosSunatEmpresa
{
    public function __construct(
        public readonly DatosEmisor $emisor,
        public readonly CertificadoDigital $certificado,
        public readonly string $usuarioSol,
        public readonly string $claveSol,
        public readonly string $endpoint,
    ) {}
}
