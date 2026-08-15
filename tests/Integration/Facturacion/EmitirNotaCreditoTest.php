<?php

declare(strict_types=1);

use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\CasosDeUso\EmitirNotaCredito;
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
        [
            'id' => (string) Str::uuid7(), 'empresa_id' => $this->empresa->id,
            'tipo_comprobante' => 'FACTURA', 'serie' => 'F001', 'correlativo_actual' => 0,
            'activa' => true, 'created_at' => now(), 'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid7(), 'empresa_id' => $this->empresa->id,
            'tipo_comprobante' => 'NOTA_CREDITO', 'serie' => 'FC01', 'correlativo_actual' => 0,
            'activa' => true, 'created_at' => now(), 'updated_at' => now(),
        ],
    ]);

    $this->facturaOriginal = app(EmitirFactura::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'F001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [new ItemInput('Servicio', 'NIU', 1.0, '100.00', '10')],
    ));
});

it('emite una nota de crédito referenciando una factura existente', function () {
    $notaCredito = app(EmitirNotaCredito::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'FC01',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [new ItemInput('Devolución parcial', 'NIU', 1.0, '100.00', '10')],
        comprobanteReferenciaId: $this->facturaOriginal->id(),
        codigoMotivo: '01',
        descripcionMotivo: 'Anulación de la operación',
    ));

    expect($notaCredito->estado())->toBe(EstadoComprobante::Registrado)
        ->and($notaCredito->referencia()?->comprobanteId())->toBe($this->facturaOriginal->id());

    $fila = ComprobanteEloquent::query()->findOrFail($notaCredito->id());
    expect($fila->comprobante_referencia_id)->toBe($this->facturaOriginal->id())
        ->and($fila->tipo_nota)->toBe('01')
        ->and($fila->motivo_nota)->toBe('Anulación de la operación');
});

it('rechaza una nota de crédito sin comprobante de referencia', function () {
    app(EmitirNotaCredito::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'FC01',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [new ItemInput('Devolución', 'NIU', 1.0, '100.00', '10')],
    ));
})->throws(ComprobanteInvalidoException::class);

it('nunca permite que una empresa emita una nota de crédito referenciando el comprobante de otra empresa', function () {
    $otraEmpresa = EmpresaEloquent::create([
        'ruc' => '20111111111',
        'razon_social' => 'Otra Empresa SAC',
        'estado' => 'ACTIVA',
    ]);

    // Serie propia y válida para otraEmpresa: si esto fallara solo por falta
    // de serie, no probaría el aislamiento multiempresa que realmente importa.
    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $otraEmpresa->id,
        'tipo_comprobante' => 'NOTA_CREDITO',
        'serie' => 'FC01',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(EmitirNotaCredito::class)->ejecutar(new EmitirComprobanteInput(
        empresaId: $otraEmpresa->id,
        serie: 'FC01',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [new ItemInput('Devolución', 'NIU', 1.0, '100.00', '10')],
        comprobanteReferenciaId: $this->facturaOriginal->id(),
        codigoMotivo: '01',
        descripcionMotivo: 'Motivo',
    ));
})->throws(ComprobanteInvalidoException::class);
