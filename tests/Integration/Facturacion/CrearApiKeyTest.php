<?php

declare(strict_types=1);

use App\Models\ApiKey as ApiKeyEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Modules\Facturacion\Application\CasosDeUso\CrearApiKey;
use Modules\Facturacion\Application\DTO\CrearApiKeyInput;
use Modules\Facturacion\Domain\Puertos\GeneradorClaveApi;

beforeEach(function () {
    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);
});

it('crea una API Key y persiste solo su hash, nunca la clave completa', function () {
    $resultado = app(CrearApiKey::class)->ejecutar(new CrearApiKeyInput(
        empresaId: $this->empresa->id,
        nombre: 'Integración principal',
        scopes: ['comprobantes:crear', 'comprobantes:leer'],
    ));

    $this->assertDatabaseHas('api_keys', [
        'id' => $resultado->apiKey->id(),
        'empresa_id' => $this->empresa->id,
        'estado' => 'ACTIVA',
    ]);

    $hashEsperado = app(GeneradorClaveApi::class)->hash($resultado->claveCompleta);
    $fila = ApiKeyEloquent::query()->find($resultado->apiKey->id());

    expect($fila->hash)->toBe($hashEsperado)
        ->and($fila->hash)->not->toBe($resultado->claveCompleta);
});
