<?php

declare(strict_types=1);

use App\Models\Cliente as ClienteEloquent;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Modules\Facturacion\Application\CasosDeUso\EmitirFactura;
use Modules\Facturacion\Application\DTO\EmitirComprobanteInput;
use Modules\Facturacion\Application\DTO\ItemInput;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;

beforeEach(function () {
    Queue::fake();

    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);

    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $this->empresa->id,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('emite una factura completa: correlativo, cálculo tributario y persistencia', function () {
    $casoDeUso = app(EmitirFactura::class);

    $comprobante = $casoDeUso->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'F001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [
            new ItemInput(
                descripcion: 'Servicio de consultoría',
                unidadMedida: 'NIU',
                cantidad: 2.0,
                valorUnitario: '100.00',
                tipoAfectacionIgv: '10',
            ),
        ],
    ));

    expect($comprobante->numero()->correlativo())->toBe(1)
        ->and((string) $comprobante->numero())->toBe('F001-00000001')
        ->and($comprobante->estado())->toBe(EstadoComprobante::Registrado)
        ->and($comprobante->totales()->total->comoString())->toBe('236.00');

    $fila = ComprobanteEloquent::query()->with(['items', 'tributos'])->findOrFail($comprobante->id());

    expect($fila->serie)->toBe('F001')
        ->and((int) $fila->correlativo)->toBe(1)
        ->and($fila->estado)->toBe('REGISTRADO')
        ->and($fila->total)->toBe('236.00')
        ->and($fila->total_igv)->toBe('36.00')
        ->and($fila->snapshot_emisor['razon_social'])->toBe('Empresa de Prueba SAC')
        ->and($fila->items)->toHaveCount(1)
        ->and($fila->tributos)->toHaveCount(1)
        ->and($fila->tributos->first()->tipo_tributo)->toBe('IGV');

    $serieFinal = DB::table('series')->where('empresa_id', $this->empresa->id)->first();
    expect((int) $serieFinal->correlativo_actual)->toBe(1);
});

it('asigna correlativos consecutivos entre emisiones sucesivas', function () {
    $casoDeUso = app(EmitirFactura::class);
    $input = fn () => new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'F001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: 'Cliente SAC',
        items: [new ItemInput('Item', 'NIU', 1.0, '10.00', '10')],
    );

    $primero = $casoDeUso->ejecutar($input());
    $segundo = $casoDeUso->ejecutar($input());

    expect($primero->numero()->correlativo())->toBe(1)
        ->and($segundo->numero()->correlativo())->toBe(2);
});

it('resuelve la razón social del receptor desde el cliente registrado si no se envía', function () {
    ClienteEloquent::query()->create([
        'empresa_id' => $this->empresa->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070970',
        'razon_social' => 'Cliente Registrado SAC',
    ]);

    $casoDeUso = app(EmitirFactura::class);

    $comprobante = $casoDeUso->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'F001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: null,
        items: [new ItemInput('Item', 'NIU', 1.0, '10.00', '10')],
    ));

    expect($comprobante->receptorRazonSocial())->toBe('Cliente Registrado SAC');
});

it('no resuelve la razón social de un cliente registrado en otra empresa', function () {
    $otraEmpresa = EmpresaEloquent::create([
        'ruc' => '20100070971',
        'razon_social' => 'Otra Empresa SAC',
        'estado' => 'ACTIVA',
    ]);
    ClienteEloquent::query()->create([
        'empresa_id' => $otraEmpresa->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070970',
        'razon_social' => 'Cliente De Otra Empresa SAC',
    ]);

    $casoDeUso = app(EmitirFactura::class);

    expect(fn () => $casoDeUso->ejecutar(new EmitirComprobanteInput(
        empresaId: $this->empresa->id,
        serie: 'F001',
        receptorTipoDocumento: '6',
        receptorNumeroDocumento: '20100070970',
        receptorRazonSocial: null,
        items: [new ItemInput('Item', 'NIU', 1.0, '10.00', '10')],
    )))->toThrow(ComprobanteInvalidoException::class);
});

it('revierte la asignación del correlativo si la validación falla', function () {
    $casoDeUso = app(EmitirFactura::class);

    try {
        $casoDeUso->ejecutar(new EmitirComprobanteInput(
            empresaId: $this->empresa->id,
            serie: 'F001',
            receptorTipoDocumento: '6',
            receptorNumeroDocumento: '20100070970',
            receptorRazonSocial: 'Cliente SAC',
            items: [], // sin items -> ValidadorFactura rechaza
        ));
    } catch (ComprobanteInvalidoException) {
        // esperado
    }

    expect(ComprobanteEloquent::query()->count())->toBe(0);

    $serieFinal = DB::table('series')->where('empresa_id', $this->empresa->id)->first();
    expect((int) $serieFinal->correlativo_actual)->toBe(0);
});
