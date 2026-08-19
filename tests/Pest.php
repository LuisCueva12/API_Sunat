<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

function generarCertificadoPemDePrueba(int $diasVigencia = 365): string
{
    $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    $csr = openssl_csr_new(
        ['commonName' => 'Empresa de Prueba SAC', 'countryName' => 'PE'],
        $llave,
    );

    $certificado = openssl_csr_sign($csr, null, $llave, $diasVigencia);

    openssl_x509_export($certificado, $pem);

    return $pem;
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

// Unit (incluye modules/Facturacion/Domain): sin RefreshDatabase — el
// dominio no toca base de datos, y así la suite unitaria corre rápido.
pest()->extend(TestCase::class)->in('Unit');
