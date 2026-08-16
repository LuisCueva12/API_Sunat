<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;

/**
 * Nunca el disco "public" (ver docs/01_ARQUITECTURA.md §21) — usa el disco
 * configurado en facturacion.storage_disk ("local" en desarrollo, "s3" en
 * producción). El control de acceso es por aplicación, nunca por nombre de
 * archivo.
 */
final class AlmacenPrivadoStorage implements AlmacenPrivado
{
    public function guardar(string $ruta, string $contenido): void
    {
        Storage::disk($this->disco())->put($ruta, $contenido);
    }

    public function leer(string $ruta): string
    {
        return Storage::disk($this->disco())->get($ruta) ?? '';
    }

    private function disco(): string
    {
        return (string) config('facturacion.storage_disk');
    }
}
