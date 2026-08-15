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
 * @property string $nombre
 * @property string $prefijo
 * @property string $hash
 * @property array<int, string> $scopes
 * @property Carbon|null $ultimo_uso_at
 * @property Carbon|null $expira_at
 * @property string $estado
 * @property Carbon|null $revocada_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'nombre', 'prefijo', 'hash', 'scopes', 'expira_at', 'estado'])]
class ApiKey extends Model
{
    use HasUuids;

    protected $table = 'api_keys';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'ultimo_uso_at' => 'datetime',
            'expira_at' => 'datetime',
            'revocada_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tieneScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function estaVigente(): bool
    {
        if ($this->estado !== 'ACTIVA') {
            return false;
        }

        return $this->expira_at === null || $this->expira_at->isFuture();
    }
}
