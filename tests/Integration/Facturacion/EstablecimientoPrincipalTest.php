<?php

declare(strict_types=1);

use App\Models\Empresa;
use App\Models\Establecimiento;

it('mantiene un solo establecimiento principal por empresa', function () {
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);
    $primero = Establecimiento::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => '0000',
        'denominacion' => 'Principal anterior',
        'es_principal' => true,
    ]);
    $segundo = Establecimiento::query()->create([
        'empresa_id' => $empresa->id,
        'codigo' => '0001',
        'denominacion' => 'Nuevo principal',
        'es_principal' => true,
    ]);

    $segundo->asegurarPrincipalUnico();

    expect($primero->fresh()->es_principal)->toBeFalse()
        ->and($segundo->fresh()->es_principal)->toBeTrue();
});
