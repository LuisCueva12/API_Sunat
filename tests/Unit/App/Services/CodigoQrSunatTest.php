<?php

declare(strict_types=1);

use App\Models\Comprobante;
use App\Services\Comprobantes\CodigoQrSunat;
use Illuminate\Support\Carbon;

it('construye el QR SUNAT en el orden y formato oficiales', function () {
    $comprobante = new Comprobante([
        'tipo' => 'FACTURA',
        'serie' => 'F001',
        'correlativo' => 154,
        'total_igv' => '28.98',
        'total' => '190.00',
        'fecha_emision' => Carbon::parse('2026-08-19'),
        'receptor_tipo_documento' => '6',
        'receptor_numero_documento' => '20100066603',
        'snapshot_emisor' => ['ruc' => '20100070970'],
    ]);

    $generador = new CodigoQrSunat;
    $cadena = $generador->cadena($comprobante, 'abcDEF123+/=');

    expect($cadena)->toBe('20100070970|01|F001|154|28.98|190.00|2026-08-19|6|20100066603|abcDEF123+/=')
        ->and($generador->png($cadena))->toStartWith("\x89PNG\r\n\x1a\n");
});

it('mapea los cuatro comprobantes soportados al catálogo 01 de SUNAT', function (string $tipo, string $codigo) {
    $comprobante = new Comprobante([
        'tipo' => $tipo,
        'serie' => 'T001',
        'correlativo' => 1,
        'total_igv' => '18.00',
        'total' => '118.00',
        'fecha_emision' => Carbon::parse('2026-08-19'),
        'receptor_tipo_documento' => '1',
        'receptor_numero_documento' => '45678912',
        'snapshot_emisor' => ['ruc' => '20100070970'],
    ]);

    expect((new CodigoQrSunat)->cadena($comprobante, 'digest'))->toStartWith("20100070970|{$codigo}|");
})->with([
    ['FACTURA', '01'],
    ['BOLETA', '03'],
    ['NOTA_CREDITO', '07'],
    ['NOTA_DEBITO', '08'],
]);
