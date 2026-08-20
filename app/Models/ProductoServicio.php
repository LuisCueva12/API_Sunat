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
 * @property string $empresa_id
 * @property string|null $codigo
 * @property string $nombre
 * @property string $tipo
 * @property string $unidad_medida
 * @property string $valor_unitario
 * @property bool $activo
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'codigo', 'nombre', 'tipo', 'unidad_medida', 'valor_unitario', 'activo'])]
final class ProductoServicio extends Model
{
    use HasUuids;

    protected $table = 'productos_servicios';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valor_unitario' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
