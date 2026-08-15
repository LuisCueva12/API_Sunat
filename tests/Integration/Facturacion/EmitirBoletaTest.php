<?php

declare(strict_types=1);

use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Application\CasosDeUso\EmitirBoleta;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Application\DTO\ItemInput;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;

beforeEach(function () {
    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);

    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $this->empresa->id,
        'tipo_comprobante' => 'BOLETA',
        'serie' => 'B001',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('emite una boleta con receptor identificado por DNI', function () {
    $comprobante = app(EmitirBoleta::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'B001',
        receptorTipoDocumento: '1',
        receptorNumeroDocumento: '12345678',
        receptorRazonSocial: 'Cliente Final',
        items: [new ItemInput('Producto', 'NIU', 1.0, '10.00', '10')],
    ));

    expect($comprobante->numero()->correlativo())->toBe(1)
        ->and($comprobante->estado())->toBe(EstadoComprobante::Registrado);

    $fila = ComprobanteEloquent::query()->findOrFail($comprobante->id());
    expect($fila->serie)->toBe('B001')
        ->and($fila->receptor_tipo_documento)->toBe('1');
});

it('permite boleta sin documento del receptor', function () {
    $comprobante = app(EmitirBoleta::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'B001',
        receptorTipoDocumento: '0',
        receptorNumeroDocumento: '',
        receptorRazonSocial: 'Cliente Varios',
        items: [new ItemInput('Producto', 'NIU', 1.0, '10.00', '10')],
    ));

    expect($comprobante->estado())->toBe(EstadoComprobante::Registrado);
});

it('rechaza emitir una boleta con receptor identificado por RUC', function () {
    app(EmitirBoleta::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'B001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Empresa Cliente SAC',
        items: [new ItemInput('Producto', 'NIU', 1.0, '10.00', '10')],
    ));
})->throws(ComprobanteInvalidoException::class);
