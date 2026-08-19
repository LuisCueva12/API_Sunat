<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;

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
