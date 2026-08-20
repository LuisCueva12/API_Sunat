<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $empresa_id
 * @property string $url
 * @property string $secreto_cifrado
 * @property array<int, string> $eventos
 * @property string $estado
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['empresa_id', 'url', 'secreto_cifrado', 'eventos', 'estado'])]
class Webhook extends Model
{
    use HasUuids;

    protected $table = 'webhooks';

    protected function casts(): array
    {
        return [
            'secreto_cifrado' => 'encrypted',
            'eventos' => 'array',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<EntregaWebhook, $this> */
    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaWebhook::class);
    }
}
