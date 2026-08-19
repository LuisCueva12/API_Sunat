<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $comprobante_id
 * @property string $empresa_id
 * @property string $tipo_evento
 * @property string|null $actor
 * @property string|null $request_id
 * @property array<string, mixed>|null $datos
 * @property Carbon $created_at
 */
class EventoComprobante extends Model
{
    protected $table = 'eventos_comprobante';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Comprobante, $this>
     */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
