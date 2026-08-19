<?php

declare(strict_types=1);

use App\Services\Certificados\GeneradorCertificadoAutofirmado;
use Modules\Facturacion\Domain\Certificados\AnalizadorCertificadoDigital;

it('genera un certificado autofirmado utilizable por el flujo beta', function () {
    $contenido = (new GeneradorCertificadoAutofirmado)->generar('20100066603');
    $preparado = (new AnalizadorCertificadoDigital)->preparar($contenido);

    expect($preparado->contenidoPem)
        ->toContain('-----BEGIN CERTIFICATE-----')
        ->toContain('-----BEGIN PRIVATE KEY-----')
        ->and($preparado->datos->fechaExpiracion > new DateTimeImmutable)->toBeTrue();
});
