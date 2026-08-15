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
 * @property string $tipo_tributo
 * @property string|null $codigo
 * @property string $base_imponible
 * @property string $monto
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['comprobante_id', 'tipo_tributo', 'codigo', 'base_imponible', 'monto'])]
class ComprobanteTributo extends Model
{
    use HasUuids;

    protected $table = 'comprobante_tributos';

    /**
     * @return BelongsTo<Comprobante, $this>
     */
    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
