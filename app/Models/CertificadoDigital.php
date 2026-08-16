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
 * contenido_cifrado/password_cifrado usan el cast 'encrypted' nativo de
 * Eloquent (Crypt de Laravel por debajo) — nunca se implementa cifrado
 * propio. El nombre de columna deja explícito que en BD vive el
 * ciphertext; leer el atributo en PHP devuelve el texto plano ya
 * descifrado de forma transparente.
 *
 * @property string $id
 * @property string $empresa_id
 * @property string|null $alias
 * @property string $contenido_cifrado
 * @property string $password_cifrado
 * @property string|null $huella_sha256
 * @property Carbon|null $fecha_emision
 * @property Carbon $fecha_expiracion
 * @property string $estado
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'alias', 'contenido_cifrado', 'password_cifrado', 'huella_sha256', 'fecha_emision', 'fecha_expiracion', 'estado'])]
#[Hidden(['contenido_cifrado', 'password_cifrado'])]
class CertificadoDigital extends Model
{
    use HasUuids;

    protected $table = 'certificados_digitales';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contenido_cifrado' => 'encrypted',
            'password_cifrado' => 'encrypted',
            'fecha_emision' => 'date',
            'fecha_expiracion' => 'date',
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
