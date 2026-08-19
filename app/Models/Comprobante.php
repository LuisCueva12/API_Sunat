<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $empresa_id
 * @property string $tipo
 * @property string $serie
 * @property int $correlativo
 * @property string $estado
 * @property string $moneda
 * @property string|null $tipo_cambio
 * @property string $receptor_tipo_documento
 * @property string $receptor_numero_documento
 * @property string $receptor_razon_social
 * @property string|null $receptor_direccion
 * @property string|null $receptor_email
 * @property Carbon $fecha_emision
 * @property Carbon|null $fecha_vencimiento
 * @property string $forma_pago
 * @property string $op_gravada
 * @property string $op_exonerada
 * @property string $op_inafecta
 * @property string $op_gratuita
 * @property string $total_igv
 * @property string $total_descuentos
 * @property string $total
 * @property string|null $comprobante_referencia_id
 * @property string|null $tipo_nota
 * @property string|null $motivo_nota
 * @property array<string, mixed> $snapshot_emisor
 * @property string|null $idempotency_key
 * @property string|null $xml_sha256
 * @property string|null $cdr_sha256
 * @property int $intentos_envio
 * @property string|null $ultimo_error
 * @property string|null $api_key_id
 * @property string|null $creado_por
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Collection<int, ComprobanteItem> $items
 * @property Collection<int, ComprobanteTributo> $tributos
 */
#[Fillable([
    'id', 'empresa_id', 'tipo', 'serie', 'correlativo', 'estado', 'moneda', 'tipo_cambio',
    'receptor_tipo_documento', 'receptor_numero_documento', 'receptor_razon_social',
    'receptor_direccion', 'receptor_email', 'fecha_emision', 'fecha_vencimiento',
    'forma_pago', 'op_gravada', 'op_exonerada', 'op_inafecta', 'op_gratuita',
    'total_igv', 'total_descuentos', 'total', 'comprobante_referencia_id',
    'tipo_nota', 'motivo_nota', 'snapshot_emisor', 'idempotency_key',
    'xml_sha256', 'cdr_sha256', 'intentos_envio', 'ultimo_error',
    'api_key_id', 'creado_por',
])]
class Comprobante extends Model
{
    use HasUuids;

    protected $table = 'comprobantes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_vencimiento' => 'date',
            'snapshot_emisor' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function referencia(): BelongsTo
    {
        return $this->belongsTo(self::class, 'comprobante_referencia_id');
    }

    /**
     * @return HasMany<ComprobanteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComprobanteItem::class)->orderBy('numero_orden');
    }

    /**
     * @return HasMany<ComprobanteTributo, $this>
     */
    public function tributos(): HasMany
    {
        return $this->hasMany(ComprobanteTributo::class);
    }
}
