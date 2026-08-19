<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;

function generarCertificadoP12DePrueba(string $password): string
{
    $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'Empresa P12 de Prueba'], $llave);
    $certificado = openssl_csr_sign($csr, null, $llave, 365);

    openssl_pkcs12_export($certificado, $contenido, $llave, $password);

    return $contenido;
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

it('convierte un certificado p12 con clave privada a pem para Greenter', function () {
    $preparado = (new AnalizadorCertificadoDigital)->preparar(
        generarCertificadoP12DePrueba('clave-p12'),
        'clave-p12',
    );

    expect($preparado->contenidoPem)
        ->toContain('-----BEGIN CERTIFICATE-----')
        ->toContain('-----BEGIN PRIVATE KEY-----')
        ->and($preparado->datos->huellaSha256)->toMatch('/^[0-9A-F]{64}$/');
});

it('rechaza un p12 cuando la contraseña es incorrecta', function () {
    (new AnalizadorCertificadoDigital)->preparar(
        generarCertificadoP12DePrueba('clave-correcta'),
        'clave-incorrecta',
    );
})->throws(CertificadoInvalidoException::class);
