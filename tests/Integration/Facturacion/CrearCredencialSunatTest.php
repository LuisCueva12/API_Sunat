<?php

declare(strict_types=1);

use App\Models\CredencialSunat as CredencialEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Modules\Facturacion\Application\CasosDeUso\CrearCredencialSunat;
use Modules\Facturacion\Application\DTO\CrearCredencialSunatInput;

beforeEach(function () {
    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);
});

it('crea y cifra una credencial SUNAT', function () {
    $credencial = app(CrearCredencialSunat::class)->ejecutar(new CrearCredencialSunatInput(
        empresaId: $this->empresa->id,
        entorno: 'BETA',
        usuarioSol: 'MODDATOS',
        claveSol: 'moddatos',
    ));

    $this->assertDatabaseHas('credenciales_sunat', [
        'id' => $credencial->id(),
        'empresa_id' => $this->empresa->id,
        'entorno' => 'BETA',
        'estado' => 'ACTIVA',
    ]);

    $crudo = DB::table('credenciales_sunat')->where('id', $credencial->id())->first();
    expect($crudo->clave_sol_cifrada)->not->toBe('moddatos');
});

it('rota la credencial existente sin crear una fila duplicada', function () {
    app(CrearCredencialSunat::class)->ejecutar(new CrearCredencialSunatInput($this->empresa->id, 'BETA', 'USUARIO1', 'CLAVE1'));
    app(CrearCredencialSunat::class)->ejecutar(new CrearCredencialSunatInput($this->empresa->id, 'BETA', 'USUARIO2', 'CLAVE2'));

    expect(CredencialEloquent::query()->where('empresa_id', $this->empresa->id)->where('entorno', 'BETA')->count())->toBe(1);

    $fila = CredencialEloquent::query()->where('empresa_id', $this->empresa->id)->where('entorno', 'BETA')->first();
    expect($fila->usuario_sol_cifrado)->toBe('USUARIO2');
});
