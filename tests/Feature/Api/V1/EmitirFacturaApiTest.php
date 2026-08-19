<?php

declare(strict_types=1);

use App\Jobs\ProcesarComprobante;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\Client as ClienteOAuth;
use Laravel\Passport\Passport;

beforeEach(function () {
    Queue::fake();
});

/**
 * @param  array<int, string>  $scopes
 * @return array{0: EmpresaEloquent, 1: ClienteOAuth}
 */
function crearEmpresaConIntegracion(
    array $scopes = ['comprobantes:crear', 'comprobantes:leer'],
    string $ruc = '20100070970',
): array {
    $empresa = EmpresaEloquent::create([
        'ruc' => $ruc,
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);

    $cliente = ClienteOAuth::forceCreate([
        'name' => 'Integración de prueba',
        'secret' => Str::random(40),
        'provider' => null,
        'redirect_uris' => [],
        'grant_types' => ['client_credentials'],
        'revoked' => false,
        'owner_type' => EmpresaEloquent::class,
        'owner_id' => $empresa->id,
        'scopes' => $scopes,
    ]);

    Passport::actingAsClient($cliente, $scopes);

    return [$empresa, $cliente];
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
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $respuesta = $this->postJson('/api/v1/facturas', payloadFacturaValida());

    $respuesta->assertStatus(202)
        ->assertJsonPath('data.tipo', 'factura')
        ->assertJsonPath('data.serie', 'F001')
        ->assertJsonPath('data.numero', 1)
        ->assertJsonPath('data.estado', 'REGISTRADO')
        ->assertJsonStructure(['data', 'meta' => ['request_id']]);

    expect(ComprobanteEloquent::query()->count())->toBe(1);
});

it('propaga el request id al procesamiento asíncrono', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $requestId = 'req-integracion-9841';

    $this->withHeaders(['X-Request-Id' => $requestId])
        ->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(202)
        ->assertHeader('X-Request-Id', $requestId)
        ->assertJsonPath('meta.request_id', $requestId);

    Queue::assertPushed(
        ProcesarComprobante::class,
        fn (ProcesarComprobante $job): bool => $job->requestId === $requestId
            && $job->empresaId === $empresa->id,
    );
});

it('programa el reintento de un comprobante en error', function () {
    [$empresa] = crearEmpresaConIntegracion(scopes: [
        'comprobantes:crear',
        'comprobantes:leer',
        'comprobantes:reintentar',
    ]);
    crearSerieFactura($empresa->id);

    $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(202);

    $comprobante = ComprobanteEloquent::query()->firstOrFail();
    $comprobante->update(['estado' => 'ERROR', 'ultimo_error' => 'SUNAT no disponible']);

    Cache::flush();
    Queue::fake();

    $requestId = 'req-reintento-9841';

    $this->withHeaders(['X-Request-Id' => $requestId])
        ->postJson("/api/v1/comprobantes/{$comprobante->id}/reintentar")
        ->assertStatus(202)
        ->assertJsonPath('data.id', $comprobante->id)
        ->assertJsonPath('data.estado', 'ERROR')
        ->assertJsonPath('data.reintento_programado', true);

    Queue::assertPushed(
        ProcesarComprobante::class,
        fn (ProcesarComprobante $job): bool => $job->comprobanteId === $comprobante->id
            && $job->requestId === $requestId,
    );
});

it('rechaza reintentar un comprobante que no está en error', function () {
    [$empresa] = crearEmpresaConIntegracion(scopes: [
        'comprobantes:crear',
        'comprobantes:reintentar',
    ]);
    crearSerieFactura($empresa->id);

    $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(202);

    $comprobante = ComprobanteEloquent::query()->firstOrFail();
    Queue::fake();

    $this->postJson("/api/v1/comprobantes/{$comprobante->id}/reintentar")
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'TRANSICION_INVALIDA');

    Queue::assertNothingPushed();
});

it('rechaza sin header de autorización', function () {
    $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(401)
        ->assertJsonPath('error.codigo', 'NO_AUTORIZADO');
});

it('rechaza con un access_token inválido', function () {
    $this->withHeader('Authorization', 'Bearer token-que-no-existe')
        ->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(401);
});

it('rechaza datos incompletos con 422 y nunca expone detalles internos', function () {
    crearEmpresaConIntegracion();

    $respuesta = $this->postJson('/api/v1/facturas', ['serie' => 'F001']);

    $respuesta->assertStatus(422)
        ->assertJsonPath('error.codigo', 'DATOS_INVALIDOS')
        ->assertJsonMissingPath('error.trace')
        ->assertJsonMissingPath('exception');
});

it('rechaza si la integración no tiene el scope requerido', function () {
    [$empresa] = crearEmpresaConIntegracion(scopes: ['comprobantes:leer']);
    crearSerieFactura($empresa->id);

    $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(403)
        ->assertJsonPath('error.codigo', 'PROHIBIDO');
});

it('es idempotente: la misma Idempotency-Key nunca duplica el comprobante', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $headers = ['Idempotency-Key' => 'venta-9841'];

    $primera = $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida());
    $segunda = $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida());

    $primera->assertStatus(202);
    $segunda->assertStatus(202);
    expect($segunda->json('data.id'))->toBe($primera->json('data.id'));
    expect(ComprobanteEloquent::query()->count())->toBe(1);
});

it('rechaza reusar una Idempotency-Key con una solicitud distinta', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $headers = ['Idempotency-Key' => 'venta-distinta'];

    $this->withHeaders($headers)->postJson('/api/v1/facturas', payloadFacturaValida())->assertStatus(202);

    $payloadDistinto = payloadFacturaValida();
    $payloadDistinto['receptor_razon_social'] = 'Otro Cliente Totalmente Distinto';

    $this->withHeaders($headers)->postJson('/api/v1/facturas', $payloadDistinto)
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'IDEMPOTENCY_KEY_CONFLICTO');
});

it('nunca permite que una empresa consulte el comprobante de otra', function () {
    [$empresaA] = crearEmpresaConIntegracion();
    crearSerieFactura($empresaA->id);
    $this->postJson('/api/v1/facturas', payloadFacturaValida());
    $comprobanteDeA = ComprobanteEloquent::query()->first();

    crearEmpresaConIntegracion(ruc: '20100070971');

    $this->getJson("/api/v1/comprobantes/{$comprobanteDeA->id}")
        ->assertStatus(404);
});
