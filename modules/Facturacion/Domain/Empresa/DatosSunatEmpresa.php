<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;

/**
 * Todo lo que GeneradorXmlFirmado y EnviadorComprobanteElectronico
 * necesitan de una empresa para operar, ya resuelto (certificado
 * descifrado, credenciales descifradas) — quien la produce es el único
 * punto que toca datos cifrados en claro, y solo vive en memoria durante
 * el pipeline de envío.
 */
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
