<?php

declare(strict_types=1);

namespace App\Services\Certificados;

use RuntimeException;

final class GeneradorCertificadoAutofirmado
{
    public function generar(string $ruc, int $diasVigencia = 365): string
    {
        $llave = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($llave === false) {
            throw new RuntimeException('No se pudo generar la clave privada para BETA.');
        }

        $csr = openssl_csr_new([
            'commonName' => "Empresa BETA {$ruc}",
            'organizationName' => 'Pruebas SUNAT BETA',
            'countryName' => 'PE',
        ], $llave);

        if ($csr === false) {
            throw new RuntimeException('No se pudo generar la solicitud del certificado para BETA.');
        }

        $certificado = openssl_csr_sign($csr, null, $llave, $diasVigencia);

        if ($certificado === false
            || ! openssl_x509_export($certificado, $certificadoPem)
            || ! openssl_pkey_export($llave, $llavePem)) {
            throw new RuntimeException('No se pudo generar el certificado autofirmado para BETA.');
        }

        return $certificadoPem.$llavePem;
    }
}
