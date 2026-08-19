<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $comprobante_id
 * @property int $intento
 * @property string $entorno
 * @property string|null $codigo_respuesta_sunat
 * @property string|null $descripcion_respuesta_sunat
 * @property array<string, mixed>|null $notas_sunat
 * @property string|null $ticket_sunat
 * @property string|null $xml_path
 * @property string|null $cdr_path
 * @property int|null $duracion_ms
 * @property string|null $error_tecnico
 * @property Carbon $created_at
 */
class EnvioSunat extends Model
{
    use HasUuids;

    protected $table = 'envios_sunat';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notas_sunat' => 'array',
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
