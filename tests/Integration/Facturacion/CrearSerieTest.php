<?php

declare(strict_types=1);

use App\Models\Empresa as EmpresaEloquent;
use App\Models\Serie as SerieEloquent;
use Illuminate\Support\Str;
use Modules\Facturacion\Application\CasosDeUso\CrearSerie;
use Modules\Facturacion\Application\DTO\CrearSerieInput;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;

beforeEach(function () {
    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);
});

it('crea y persiste una serie para una empresa activa', function () {
    $serie = app(CrearSerie::class)->ejecutar(new CrearSerieInput(
        empresaId: $this->empresa->id,
        tipoComprobante: 'FACTURA',
        serie: 'F001',
    ));

    expect($serie->estaActiva())->toBeTrue();

    $this->assertDatabaseHas('series', [
        'id' => $serie->id(),
        'empresa_id' => $this->empresa->id,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
        'activa' => true,
    ]);
});

it('rechaza crear una serie para una empresa inexistente', function () {
    app(CrearSerie::class)->ejecutar(new CrearSerieInput(
        empresaId: (string) Str::uuid7(),
        tipoComprobante: 'FACTURA',
        serie: 'F001',
    ));
})->throws(EmpresaInvalidaException::class);

it('rechaza crear una serie duplicada para el mismo tipo de comprobante', function () {
    SerieEloquent::create([
        'empresa_id' => $this->empresa->id,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
        'activa' => true,
    ]);

    app(CrearSerie::class)->ejecutar(new CrearSerieInput(
        empresaId: $this->empresa->id,
        tipoComprobante: 'FACTURA',
        serie: 'F001',
    ));
})->throws(SerieInvalidaException::class);
