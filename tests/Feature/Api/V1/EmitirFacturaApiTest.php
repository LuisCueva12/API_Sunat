<?php

declare(strict_types=1);

use App\Models\ApiKey as ApiKeyEloquent;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use App\Services\ApiKeys\GeneradorApiKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function crearEmpresaConApiKey(array $scopes = ['comprobantes:crear', 'comprobantes:leer']): array
{
    $empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);

    $resultado = (new GeneradorApiKey)->generar();

    ApiKeyEloquent::create([
        'empresa_id' => $empresa->id,
        'nombre' => 'Key de prueba',
        'prefijo' => $resultado->prefijo,
        'hash' => $resultado->hash,
        'scopes' => $scopes,
        'estado' => 'ACTIVA',
    ]);

    return [$empresa, $resultado->keyCompleta];
}

function crearSerieFactura(string $empresaId, string $serie = 'F001'): void
{
    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $empresaId,
        'tipo_comprobante' => 'FACTURA',
        'serie' => $serie,
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function payloadFacturaValida(): array
{
    return [
        'serie' => 'F001',
        'receptor_tipo_documento' => '6',
        'receptor_numero_documento' => '20100070970',
        'receptor_razon_social' => 'Cliente SAC',
        'items' => [
            ['descripcion' => 'Servicio', 'unidad_medida' => 'NIU', 'cantidad' => 1, 'valor_unitario' => '100.00', 'tipo_afectacion_igv' => '10'],
        ],
    ];
}

it('emite una factura vía API con autenticación correcta', function () {
    [$empresa, $apiKey] = crearEmpresaConApiKey();
    crearSerieFactura($empresa->id);

    $respuesta = $this->withHeader('Authorization', "Bearer {$apiKey}")
        ->postJson('/api/v1/facturas', payloadFacturaValida());

    $respuesta->assertStatus(202)
        ->assertJsonPath('data.tipo', 'factura')
        ->assertJsonPath('data.serie', 'F001')
        ->assertJsonPath('data.numero', 1)
        ->assertJsonPath('data.estado', 'REGISTRADO')
        ->assertJsonStructure(['data', 'meta' => ['request_id']]);

    expect(ComprobanteEloquent::query()->count())->toBe(1);
});

it('rechaza sin header de autorización', function () {
    $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(401)
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
});

it('rechaza con una api key inválida', function () {
    $this->withHeader('Authorization', 'Bearer fe_live_no-existe')
        ->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(401);
});

it('rechaza datos incompletos con 422 y nunca expone detalles internos', function () {
    [, $apiKey] = crearEmpresaConApiKey();

    $respuesta = $this->withHeader('Authorization', "Bearer {$apiKey}")
        ->postJson('/api/v1/facturas', ['serie' => 'F001']);

    $respuesta->assertStatus(422)
        ->assertJsonPath('error.codigo', 'DATOS_INVALIDOS')
        ->assertJsonMissingPath('error.trace')
        ->assertJsonMissingPath('exception');
});

it('rechaza si la api key no tiene el scope requerido', function () {
    [$empresa, $apiKey] = crearEmpresaConApiKey(scopes: ['comprobantes:leer']);
    crearSerieFactura($empresa->id);

    $this->withHeader('Authorization', "Bearer {$apiKey}")
        ->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(403)
        ->assertJsonPath('error.codigo', 'PROHIBIDO');
});

it('es idempotente: la misma Idempotency-Key nunca duplica el comprobante', function () {
    [$empresa, $apiKey] = crearEmpresaConApiKey();
    crearSerieFactura($empresa->id);

    $headers = ['Authorization' => "Bearer {$apiKey}", 'Idempotency-Key' => 'venta-9841'];

    $primera = $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida());
    $segunda = $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida());

    $primera->assertStatus(202);
    $segunda->assertStatus(202);
    expect($segunda->json('data.id'))->toBe($primera->json('data.id'));
    expect(ComprobanteEloquent::query()->count())->toBe(1);
});

it('rechaza reusar una Idempotency-Key con una solicitud distinta', function () {
    [$empresa, $apiKey] = crearEmpresaConApiKey();
    crearSerieFactura($empresa->id);

    $headers = ['Authorization' => "Bearer {$apiKey}", 'Idempotency-Key' => 'venta-distinta'];

    $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida())->assertStatus(202);

    $payloadDistinto = payloadFacturaValida();
    $payloadDistinto['receptor_razon_social'] = 'Otro Cliente Totalmente Distinto';

    $this->withHeaders($headers)->postJson('/api/v1/facturas', $payloadDistinto)
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'IDEMPOTENCY_KEY_CONFLICTO');
});

it('nunca permite que una empresa consulte el comprobante de otra', function () {
    [$empresaA, $apiKeyA] = crearEmpresaConApiKey();
    crearSerieFactura($empresaA->id);
    $this->withHeader('Authorization', "Bearer {$apiKeyA}")
        ->postJson('/api/v1/facturas', payloadFacturaValida());
    $comprobanteDeA = ComprobanteEloquent::query()->first();

    [, $apiKeyB] = crearEmpresaConApiKey();

    $this->withHeader('Authorization', "Bearer {$apiKeyB}")
        ->getJson("/api/v1/comprobantes/{$comprobanteDeA->id}")
        ->assertStatus(404);
});
