<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'codigo',
    'denominacion',
    'ubigeo',
    'departamento',
    'provincia',
    'distrito',
    'direccion',
    'es_principal',
])]
class Establecimiento extends Model
{
    use HasUuids;

    protected $table = 'establecimientos';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['es_principal' => 'boolean'];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<Serie, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    public function asegurarPrincipalUnico(): void
    {
        if (! $this->es_principal) {
            return;
        }

        self::query()
            ->where('empresa_id', $this->empresa_id)
            ->whereKeyNot($this->getKey())
            ->update(['es_principal' => false]);
    }
}
