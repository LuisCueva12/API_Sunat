<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;

function generarCertificadoPemDePrueba(int $diasVigencia = 365): string
{
    $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    $csr = openssl_csr_new(
        ['commonName' => 'Empresa de Prueba SAC', 'countryName' => 'PE'],
        $llave,
    );

    $cert = openssl_csr_sign($csr, null, $llave, $diasVigencia);

    openssl_x509_export($cert, $pem);

    return $pem;
}

it('analiza un certificado válido y extrae huella y vigencia', function () {
    $pem = generarCertificadoPemDePrueba();

    $datos = (new AnalizadorCertificadoDigital)->analizar($pem);

    expect($datos->huellaSha256)->toMatch('/^[0-9A-F]{64}$/')
        ->and($datos->fechaExpiracion)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($datos->fechaExpiracion > new DateTimeImmutable)->toBeTrue();
});

it('rechaza contenido que no es un certificado X.509', function () {
    (new AnalizadorCertificadoDigital)->analizar('esto no es un certificado');
})->throws(CertificadoInvalidoException::class);
