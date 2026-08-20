<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $webhook_id
 * @property string $comprobante_id
 * @property array<string, mixed> $payload
 * @property int $intento
 * @property string $estado
 * @property int|null $http_status
 * @property string|null $respuesta_body
 * @property Carbon|null $proximo_intento_at
 * @property Webhook $webhook
 * @property Comprobante $comprobante
 */
#[Fillable([
    'webhook_id', 'comprobante_id', 'payload', 'intento', 'estado',
    'http_status', 'respuesta_body', 'proximo_intento_at',
])]
class EntregaWebhook extends Model
{
    protected $table = 'entregas_webhook';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'proximo_intento_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    /** @return BelongsTo<Comprobante, $this> */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
