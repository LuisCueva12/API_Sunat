<?php

declare(strict_types=1);

use App\Jobs\EnviarWebhook;
use App\Models\Empresa;
use App\Models\EntregaWebhook;
use App\Models\Webhook;
use App\Services\Webhooks\ValidadorUrlWebhook;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('entrega el webhook con el cuerpo exacto y una firma HMAC verificable', function () {
    Http::fake(['https://93.184.216.34/*' => Http::response('', 204)]);

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Webhook SAC',
        'estado' => 'ACTIVA',
    ]);
    $comprobante = crearComprobantePanel($empresa->id);
    $secreto = 'secreto-webhook-de-al-menos-32-caracteres';
    $webhook = Webhook::query()->create([
        'empresa_id' => $empresa->id,
        'url' => 'https://93.184.216.34/eventos',
        'secreto_cifrado' => $secreto,
        'eventos' => ['comprobante.aceptado'],
        'estado' => 'ACTIVO',
    ]);
    $payload = [
        'evento' => 'comprobante.aceptado',
        'comprobante_id' => $comprobante->id,
        'estado' => 'ACEPTADO',
    ];
    $entrega = EntregaWebhook::query()->create([
        'webhook_id' => $webhook->id,
        'comprobante_id' => $comprobante->id,
        'payload' => $payload,
        'estado' => 'PENDIENTE',
    ]);

    (new EnviarWebhook($entrega->id))->handle(app(ValidadorUrlWebhook::class));

    expect($entrega->fresh()->estado)->toBe('ENTREGADO');

    Http::assertSent(function (Request $request) use ($secreto): bool {
        $timestamp = $request->header('X-Facturacion-Timestamp')[0] ?? '';
        $firma = $request->header('X-Facturacion-Signature')[0] ?? '';

        return $firma === 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secreto);
    });
});

it('rechaza destinos webhook en redes privadas para evitar SSRF', function () {
    app(ValidadorUrlWebhook::class)->validar('https://127.0.0.1/webhook');
})->throws(InvalidArgumentException::class, 'red privada o reservada');
