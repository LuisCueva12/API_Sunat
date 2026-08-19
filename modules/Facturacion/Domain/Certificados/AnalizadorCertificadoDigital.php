<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Certificados;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;
use OpenSSLCertificate;

final class AnalizadorCertificadoDigital
{
    public function preparar(string $contenido, string $password = ''): CertificadoDigitalPreparado
    {
        if (str_contains($contenido, '-----BEGIN CERTIFICATE-----')) {
            $certificadoPem = $contenido;
            $llavePrivada = @openssl_pkey_get_private($contenido, $password);
        } else {
            $componentes = [];

            if (! @openssl_pkcs12_read($contenido, $componentes, $password)) {
                throw new CertificadoInvalidoException('No se pudo abrir el certificado PKCS#12; verifica el archivo y su contraseña.');
            }

            $certificadoPem = is_string($componentes['cert'] ?? null) ? $componentes['cert'] : '';
            $llavePrivada = @openssl_pkey_get_private($componentes['pkey'] ?? '', $password);
        }

        $certificado = @openssl_x509_read($certificadoPem);

        if ($certificado === false || $llavePrivada === false) {
            throw new CertificadoInvalidoException('El certificado debe incluir una clave privada válida.');
        }

        if (! openssl_x509_check_private_key($certificado, $llavePrivada)) {
            throw new CertificadoInvalidoException('La clave privada no corresponde al certificado digital.');
        }

        if (! openssl_x509_export($certificado, $certificadoNormalizado)
            || ! openssl_pkey_export($llavePrivada, $llaveNormalizada)) {
            throw new CertificadoInvalidoException('No se pudo normalizar el certificado digital.');
        }

        return new CertificadoDigitalPreparado(
            $certificadoNormalizado.$llaveNormalizada,
            $this->analizar($certificadoNormalizado),
        );
    }

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
