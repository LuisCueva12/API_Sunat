<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Facturacion\Domain\Puertos\GestorTransacciones;

final class GestorTransaccionesDb implements GestorTransacciones
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutar(Closure $operacion): mixed
    {
        return DB::transaction($operacion);
    }
}
