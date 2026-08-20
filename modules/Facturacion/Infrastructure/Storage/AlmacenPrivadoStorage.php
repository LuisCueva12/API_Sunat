<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Storage;

use Illuminate\Support\Facades\Storage;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;

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

    public function existe(string $ruta): bool
    {
        return Storage::disk($this->disco())->exists($ruta);
    }

    private function disco(): string
    {
        return (string) config('facturacion.storage_disk');
    }
}
