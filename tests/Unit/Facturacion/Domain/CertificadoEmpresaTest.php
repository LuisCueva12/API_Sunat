<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Empresa\CertificadoEmpresa;
use Modules\Facturacion\Domain\Empresa\EstadoCertificado;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;

function registrarCertificadoDePrueba(?DateTimeImmutable $fechaExpiracion = null): CertificadoEmpresa
{
    return CertificadoEmpresa::registrar(
        id: 'certificado-1',
        empresaId: 'empresa-1',
        alias: 'Principal',
        contenidoPem: '-----BEGIN CERTIFICATE-----...',
        passwordCertificado: 'secreto',
        huellaSha256: str_repeat('A', 64),
        fechaEmision: new DateTimeImmutable('-1 day'),
        fechaExpiracion: $fechaExpiracion ?? new DateTimeImmutable('+1 year'),
    );
}

it('se registra activo y vigente', function () {
    $certificado = registrarCertificadoDePrueba();

    expect($certificado->estado())->toBe(EstadoCertificado::Activo)
        ->and($certificado->estaVigente())->toBeTrue();
});

it('rechaza registrar un certificado ya vencido', function () {
    registrarCertificadoDePrueba(new DateTimeImmutable('-1 day'));
})->throws(CertificadoInvalidoException::class);

it('puede reemplazarse una sola vez', function () {
    $certificado = registrarCertificadoDePrueba();

    $certificado->reemplazar();

    expect($certificado->estado())->toBe(EstadoCertificado::Reemplazado)
        ->and($certificado->estaVigente())->toBeFalse();

    $certificado->reemplazar();
})->throws(CertificadoInvalidoException::class);

it('puede revocarse', function () {
    $certificado = registrarCertificadoDePrueba();

    $certificado->revocar();

    expect($certificado->estado())->toBe(EstadoCertificado::Revocado)
        ->and($certificado->estaVigente())->toBeFalse();
});
