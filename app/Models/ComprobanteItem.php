<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $comprobante_id
 * @property int $numero_orden
 * @property string|null $codigo_producto
 * @property string $descripcion
 * @property string $unidad_medida
 * @property string $cantidad
 * @property string $valor_unitario
 * @property string $precio_unitario
 * @property string $tipo_afectacion_igv
 * @property string $monto_igv
 * @property string $monto_valor_venta
 * @property string $descuento
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'comprobante_id', 'numero_orden', 'codigo_producto', 'descripcion',
    'unidad_medida', 'cantidad', 'valor_unitario', 'precio_unitario',
    'tipo_afectacion_igv', 'monto_igv', 'monto_valor_venta', 'descuento',
])]
class ComprobanteItem extends Model
{
    use HasUuids;

    protected $table = 'comprobante_items';

    /**
     * @return BelongsTo<Comprobante, $this>
     */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
