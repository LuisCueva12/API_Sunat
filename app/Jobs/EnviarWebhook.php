<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EntregaWebhook;
use App\Services\Webhooks\ValidadorUrlWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class EnviarWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $entregaId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    /** @throws ConnectionException */
    public function handle(ValidadorUrlWebhook $validador): void
    {
        $entrega = EntregaWebhook::query()->with('webhook')->findOrFail($this->entregaId);

        if ($entrega->estado === 'ENTREGADO') {
            return;
        }

        $webhook = $entrega->webhook;

        if ($webhook->estado !== 'ACTIVO') {
            $entrega->update(['estado' => 'AGOTADO', 'respuesta_body' => 'Webhook inactivo.']);

            return;
        }

        $direccion = $validador->resolverDireccionPublica($webhook->url);
        $host = (string) parse_url($webhook->url, PHP_URL_HOST);
        $puerto = (int) (parse_url($webhook->url, PHP_URL_PORT) ?: 443);
        $body = json_encode($entrega->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;
        $firma = hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secreto_cifrado);

        $respuesta = Http::connectTimeout(5)
            ->timeout(10)
            ->withOptions([
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$puerto}:{$direccion}"]],
            ])
            ->withHeaders([
                'X-Facturacion-Signature' => 'sha256='.$firma,
                'X-Facturacion-Timestamp' => $timestamp,
            ])
            ->withBody($body, 'application/json')
            ->post($webhook->url);

        $entrega->update([
            'intento' => $this->attempts(),
            'estado' => $respuesta->successful() ? 'ENTREGADO' : 'FALLIDO',
            'http_status' => $respuesta->status(),
            'respuesta_body' => mb_substr($respuesta->body(), 0, 4000),
            'proximo_intento_at' => $respuesta->successful() ? null : now()->addSeconds($this->siguienteBackoff()),
        ]);

        if (! $respuesta->successful()) {
            throw new RuntimeException("El webhook respondió HTTP {$respuesta->status()}.");
        }
    }

    public function failed(?Throwable $exception): void
    {
        EntregaWebhook::query()->whereKey($this->entregaId)->update([
            'estado' => 'AGOTADO',
            'respuesta_body' => mb_substr($exception?->getMessage() ?? 'Entrega agotada.', 0, 4000),
            'proximo_intento_at' => null,
        ]);
    }

    private function siguienteBackoff(): int
    {
        $backoff = $this->backoff();

        return $backoff[min(max($this->attempts() - 1, 0), count($backoff) - 1)];
    }
}
