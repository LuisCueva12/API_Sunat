<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $empresa_id
 * @property string $entorno
 * @property string $usuario_sol_cifrado
 * @property string $clave_sol_cifrada
 * @property string $estado
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'entorno', 'usuario_sol_cifrado', 'clave_sol_cifrada', 'estado'])]
#[Hidden(['usuario_sol_cifrado', 'clave_sol_cifrada'])]
class CredencialSunat extends Model
{
    use HasUuids;

    protected $table = 'credenciales_sunat';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usuario_sol_cifrado' => 'encrypted',
            'clave_sol_cifrada' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
