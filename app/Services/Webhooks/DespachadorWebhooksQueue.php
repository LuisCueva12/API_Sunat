<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Jobs\EnviarWebhook;
use App\Models\EntregaWebhook;
use App\Models\Webhook;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Puertos\DespachadorWebhooks;
use Throwable;

final class DespachadorWebhooksQueue implements DespachadorWebhooks
{
    public function despacharEventoTerminal(Comprobante $comprobante): void
    {
        try {
            $evento = 'comprobante.'.strtolower($comprobante->estado()->value);
            $payload = [
                'evento' => $evento,
                'id' => $comprobante->id().':'.$comprobante->estado()->value,
                'comprobante_id' => $comprobante->id(),
                'estado' => $comprobante->estado()->value,
                'tipo' => strtolower($comprobante->tipo()->value),
                'serie' => $comprobante->numero()->serie()->valor(),
                'numero' => $comprobante->numero()->correlativo(),
                'ocurrido_en' => now()->toIso8601String(),
            ];

            Webhook::query()
                ->where('empresa_id', $comprobante->empresaId())
                ->where('estado', 'ACTIVO')
                ->get()
                ->filter(static fn (Webhook $webhook): bool => in_array($evento, $webhook->eventos ?? [], true))
                ->each(function (Webhook $webhook) use ($comprobante, $payload): void {
                    $entrega = EntregaWebhook::query()->create([
                        'webhook_id' => $webhook->id,
                        'comprobante_id' => $comprobante->id(),
                        'payload' => $payload,
                        'estado' => 'PENDIENTE',
                    ]);

                    EnviarWebhook::dispatch($entrega->id);
                });
        } catch (Throwable $e) {
            report($e);
        }
    }
}
