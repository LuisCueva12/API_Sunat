<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ayuda de captura para la emisión. No representa inventario ni un producto.
 *
 * @property string $id
 * @property string $empresa_id
 * @property string $descripcion
 * @property string $unidad_medida
 * @property string $valor_unitario
 * @property string $tipo_afectacion_igv
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['id', 'empresa_id', 'descripcion', 'unidad_medida', 'valor_unitario', 'tipo_afectacion_igv'])]
final class ConceptoFrecuente extends Model
{
    use HasUuids;

    protected $table = 'conceptos_frecuentes';

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
