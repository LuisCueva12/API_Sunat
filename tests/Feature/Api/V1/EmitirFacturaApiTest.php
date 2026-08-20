<?php

declare(strict_types=1);

use App\Jobs\ProcesarComprobante;
use App\Models\Cliente as ClienteEloquent;
use App\Models\Comprobante as ComprobanteEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

function crearSerieComprobante(string $empresaId, string $tipo, string $serie): void
{
    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $empresaId,
        'tipo_comprobante' => $tipo,
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

it('aísla la Idempotency-Key por endpoint', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    DB::table('series')->insert([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $empresa->id,
        'tipo_comprobante' => 'BOLETA',
        'serie' => 'B001',
        'correlativo_actual' => 0,
        'activa' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $headers = ['Idempotency-Key' => 'venta-compartida'];

    $this->withHeaders($headers)
        ->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(202)
        ->assertJsonPath('data.tipo', 'factura');

    $boleta = payloadFacturaValida();
    $boleta['serie'] = 'B001';
    $boleta['receptor_tipo_documento'] = '1';
    $boleta['receptor_numero_documento'] = '12345678';

    $this->withHeaders($headers)
        ->postJson('/api/v1/boletas', $boleta)
        ->assertStatus(202)
        ->assertJsonPath('data.tipo', 'boleta');

    expect(ComprobanteEloquent::query()->count())->toBe(2);
});

it('resuelve receptor_razon_social del maestro de Clientes cuando no se envía', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    ClienteEloquent::query()->create([
        'empresa_id' => $empresa->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070970',
        'razon_social' => 'Cliente Registrado SAC',
    ]);

    $payload = payloadFacturaValida();
    unset($payload['receptor_razon_social']);

    $this->postJson('/api/v1/facturas', $payload)->assertStatus(202);

    $comprobante = ComprobanteEloquent::query()->firstOrFail();
    expect($comprobante->receptor_razon_social)->toBe('Cliente Registrado SAC');
});

it('rechaza con 422 si no envía receptor_razon_social y no hay cliente registrado con ese documento', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $payload = payloadFacturaValida();
    unset($payload['receptor_razon_social']);

    $this->postJson('/api/v1/facturas', $payload)
        ->assertStatus(422)
        ->assertJsonPath('error.codigo', 'COMPROBANTE_INVALIDO');

    expect(ComprobanteEloquent::query()->count())->toBe(0);
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

it('expone listado, detalle completo, eventos, empresa y archivos privados del tenant', function () {
    Storage::fake('local');
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);

    $this->postJson('/api/v1/facturas', payloadFacturaValida())->assertStatus(202);
    $comprobante = ComprobanteEloquent::query()->firstOrFail();

    $this->getJson('/api/v1/comprobantes')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/comprobantes/{$comprobante->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonCount(1, 'data.tributos')
        ->assertJsonPath('data.totales.total', '118.00')
        ->assertJsonMissingPath('data.procesamiento.ultimo_error');

    DB::table('eventos_comprobante')->insert([
        'comprobante_id' => $comprobante->id,
        'empresa_id' => $empresa->id,
        'tipo_evento' => 'ERROR',
        'actor' => 'WORKER',
        'datos' => json_encode(['mensaje' => 'ruta /servidor/secreta', 'intento' => 1]),
        'created_at' => now()->addSecond(),
    ]);

    $this->getJson("/api/v1/comprobantes/{$comprobante->id}/eventos")
        ->assertOk()
        ->assertJsonPath('data.0.tipo', 'ERROR')
        ->assertJsonPath('data.0.datos.intento', 1)
        ->assertJsonMissingPath('data.0.datos.mensaje');

    $this->getJson('/api/v1/empresas/actual')
        ->assertOk()
        ->assertJsonPath('data.id', $empresa->id)
        ->assertJsonPath('data.ruc', '20100070970');

    $rutaBase = "empresas/{$empresa->id}/comprobantes/{$comprobante->fecha_emision->format('Y/m')}/{$comprobante->id}";
    Storage::disk('local')->put("{$rutaBase}/comprobante.xml", '<Invoice/>');
    Storage::disk('local')->put("{$rutaBase}/cdr.zip", 'cdr-binario');

    $this->get("/api/v1/comprobantes/{$comprobante->id}/xml")
        ->assertOk()
        ->assertHeader('content-type', 'application/xml; charset=UTF-8');

    $this->get("/api/v1/comprobantes/{$comprobante->id}/cdr")
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');

    $this->getJson("/api/v1/comprobantes/{$comprobante->id}/pdf")
        ->assertStatus(409)
        ->assertJsonPath('error.codigo', 'PDF_NO_DISPONIBLE');

    $comprobante->update(['estado' => 'ACEPTADO']);
    Storage::disk('local')->put("{$rutaBase}/representacion-ticket-v3.pdf", '%PDF-1.4 prueba');

    $this->get("/api/v1/comprobantes/{$comprobante->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('emite boleta y notas de crédito y débito por sus endpoints HTTP', function () {
    [$empresa] = crearEmpresaConIntegracion();
    crearSerieFactura($empresa->id);
    crearSerieComprobante($empresa->id, 'BOLETA', 'B001');
    crearSerieComprobante($empresa->id, 'NOTA_CREDITO', 'FC01');
    crearSerieComprobante($empresa->id, 'NOTA_DEBITO', 'FD01');

    $factura = $this->postJson('/api/v1/facturas', payloadFacturaValida())
        ->assertStatus(202)
        ->json('data.id');
    ComprobanteEloquent::query()->whereKey($factura)->update(['estado' => 'ACEPTADO']);

    $boleta = payloadFacturaValida();
    $boleta['serie'] = 'B001';
    $boleta['receptor_tipo_documento'] = '1';
    $boleta['receptor_numero_documento'] = '12345678';
    $this->postJson('/api/v1/boletas', $boleta)
        ->assertStatus(202)
        ->assertJsonPath('data.tipo', 'boleta');

    $nota = payloadFacturaValida();
    $nota['comprobante_referencia_id'] = $factura;
    $nota['descripcion_motivo'] = 'Ajuste del comprobante';

    $nota['serie'] = 'FC01';
    $nota['codigo_motivo'] = '06';
    $this->postJson('/api/v1/notas-credito', $nota)
        ->assertStatus(202)
        ->assertJsonPath('data.tipo', 'nota_credito');

    $nota['serie'] = 'FD01';
    $nota['codigo_motivo'] = '02';
    $this->postJson('/api/v1/notas-debito', $nota)
        ->assertStatus(202)
        ->assertJsonPath('data.tipo', 'nota_debito');
});
