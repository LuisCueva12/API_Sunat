<?php

declare(strict_types=1);

use App\Models\Empresa as EmpresaEloquent;
use Modules\Facturacion\Application\CasosDeUso\CrearEmpresa;
use Modules\Facturacion\Application\DTO\CrearEmpresaInput;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;

it('crea y persiste una empresa', function () {
    $empresa = app(CrearEmpresa::class)->ejecutar(new CrearEmpresaInput(
        ruc: '20100070970',
        razonSocial: 'Empresa de Prueba SAC',
        nombreComercial: 'Prueba',
    ));

    expect($empresa->estaActiva())->toBeTrue();

    $this->assertDatabaseHas('empresas', [
        'id' => $empresa->id(),
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'nombre_comercial' => 'Prueba',
        'estado' => 'ACTIVA',
    ]);
});

it('rechaza crear una empresa con un RUC ya registrado', function () {
    EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Existente SAC',
        'estado' => 'ACTIVA',
    ]);

    app(CrearEmpresa::class)->ejecutar(new CrearEmpresaInput(
        ruc: '20100070970',
        razonSocial: 'Otra Empresa SAC',
    ));
})->throws(EmpresaInvalidaException::class);
