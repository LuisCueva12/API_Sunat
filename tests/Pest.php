<?php

declare(strict_types=1);

use App\Models\Comprobante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

function crearComprobantePanel(string $empresaId, array $overrides = []): Comprobante
{
    return Comprobante::query()->create(array_merge([
        'empresa_id' => $empresaId,
        'tipo' => 'FACTURA',
        'serie' => 'F001',
        'correlativo' => 1,
        'estado' => 'ACEPTADO',
        'fecha_emision' => now()->toDateString(),
        'receptor_tipo_documento' => '6',
        'receptor_numero_documento' => '20100070970',
        'receptor_razon_social' => 'Cliente de prueba SAC',
        'op_gravada' => '100.00',
        'total_igv' => '18.00',
        'total' => '118.00',
        'snapshot_emisor' => ['ruc' => '20100070970'],
    ], $overrides));
}

function generarCertificadoPemDePrueba(int $diasVigencia = 365): string
{
    $llave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);

    $csr = openssl_csr_new(
        ['commonName' => 'Empresa de Prueba SAC', 'countryName' => 'PE'],
        $llave,
    );

    $certificado = openssl_csr_sign($csr, null, $llave, $diasVigencia);

    openssl_x509_export($certificado, $pem);
    openssl_pkey_export($llave, $llavePem);

    return $pem.$llavePem;
}

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Integration');

pest()->extend(TestCase::class)->in('Unit');
