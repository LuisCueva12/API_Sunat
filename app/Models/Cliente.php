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
 * @property string $tipo_documento
 * @property string $numero_documento
 * @property string $razon_social
 * @property string|null $direccion
 * @property string|null $email
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['id', 'empresa_id', 'tipo_documento', 'numero_documento', 'razon_social', 'direccion', 'email'])]
class Cliente extends Model
{
    use HasUuids;

    protected $table = 'clientes';

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
