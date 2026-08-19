<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Certificados;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;
use OpenSSLCertificate;

final class AnalizadorCertificadoDigital
{
    public function analizar(string $contenidoPem): DatosCertificadoAnalizado
    {
        $recurso = @openssl_x509_read($contenidoPem);

        if ($recurso === false) {
            throw new CertificadoInvalidoException('El contenido proporcionado no es un certificado X.509 válido.');
        }

        $datos = openssl_x509_parse($recurso);

        if ($datos === false || ! isset($datos['validFrom_time_t'], $datos['validTo_time_t'])) {
            throw new CertificadoInvalidoException('No se pudo leer la vigencia del certificado.');
        }

        $huella = $this->calcularHuella($recurso);

        return new DatosCertificadoAnalizado(
            huellaSha256: $huella,
            fechaEmision: new DateTimeImmutable('@'.$datos['validFrom_time_t']),
            fechaExpiracion: new DateTimeImmutable('@'.$datos['validTo_time_t']),
        );
    }

    private function calcularHuella(OpenSSLCertificate $recurso): string
    {
        $huella = openssl_x509_fingerprint($recurso, 'sha256');

        if ($huella === false) {
            throw new CertificadoInvalidoException('No se pudo calcular la huella del certificado.');
        }

        return strtoupper($huella);
    }
}
