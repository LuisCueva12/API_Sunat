<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\Serie as SerieVO;
use Modules\Facturacion\Infrastructure\Persistencia\Eloquent\AsignadorCorrelativoPostgres;

beforeEach(function () {
    $this->empresaId = (string) Str::uuid();

    DB::table('empresas')->insert([
        'id' => $this->empresaId,
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de prueba',
        'estado' => 'ACTIVA',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->asignador = new AsignadorCorrelativoPostgres;
});

it('asigna correlativos consecutivos empezando en 1', function () {
    DB::table('series')->insert([
        'id' => (string) Str::uuid(),
        'empresa_id' => $this->empresaId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $primero = $this->asignador->asignar($this->empresaId, TipoComprobante::Factura, new SerieVO('F001'));
    $segundo = $this->asignador->asignar($this->empresaId, TipoComprobante::Factura, new SerieVO('F001'));

    expect($primero->correlativo())->toBe(1)
        ->and($segundo->correlativo())->toBe(2)
        ->and((string) $primero)->toBe('F001-00000001');
});

it('mantiene contadores independientes por serie', function () {
    DB::table('series')->insert([
        ['id' => (string) Str::uuid(), 'empresa_id' => $this->empresaId, 'tipo_comprobante' => 'FACTURA', 'serie' => 'F001', 'correlativo_actual' => 10, 'activa' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => (string) Str::uuid(), 'empresa_id' => $this->empresaId, 'tipo_comprobante' => 'BOLETA', 'serie' => 'B001', 'correlativo_actual' => 0, 'activa' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $factura = $this->asignador->asignar($this->empresaId, TipoComprobante::Factura, new SerieVO('F001'));
    $boleta = $this->asignador->asignar($this->empresaId, TipoComprobante::Boleta, new SerieVO('B001'));

    expect($factura->correlativo())->toBe(11)
        ->and($boleta->correlativo())->toBe(1);
});

it('rechaza asignar sobre una serie inexistente', function () {
    $this->asignador->asignar($this->empresaId, TipoComprobante::Factura, new SerieVO('F999'));
})->throws(SerieInvalidaException::class);

it('rechaza asignar sobre una serie inactiva', function () {
    DB::table('series')->insert([
        'id' => (string) Str::uuid(),
        'empresa_id' => $this->empresaId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F002',
        'correlativo_actual' => 0,
        'activa' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->asignador->asignar($this->empresaId, TipoComprobante::Factura, new SerieVO('F002'));
})->throws(SerieInvalidaException::class);
