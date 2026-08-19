<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $ruc
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string $estado
 * @property array<string, mixed>|null $configuracion
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['id', 'ruc', 'razon_social', 'nombre_comercial', 'estado', 'configuracion'])]
class Empresa extends Model
{
    use HasUuids;

    protected $table = 'empresas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'configuracion' => 'array',
        ];
    }

    /**
     * @return HasMany<Comprobante, $this>
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /** @return HasMany<Establecimiento, $this> */
    public function establecimientos(): HasMany
    {
        return $this->hasMany(Establecimiento::class);
    }

    /** @return HasMany<Serie, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(Serie::class);
    }

    /** @return HasMany<CertificadoDigital, $this> */
    public function certificados(): HasMany
    {
        return $this->hasMany(CertificadoDigital::class);
    }

    /** @return HasMany<CredencialSunat, $this> */
    public function credencialesSunat(): HasMany
    {
        return $this->hasMany(CredencialSunat::class);
    }

    /** @return HasMany<ApiKey, $this> */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }
}
