<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $empresa_id
 * @property string|null $establecimiento_id
 * @property string $tipo_comprobante
 * @property string $serie
 * @property int $correlativo_actual
 * @property bool $activa
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'establecimiento_id', 'tipo_comprobante', 'serie', 'correlativo_actual', 'activa'])]
class Serie extends Model
{
    use HasUuids;

    protected $table = 'series';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correlativo_actual' => 'integer',
            'activa' => 'boolean',
        ];
    }
}
