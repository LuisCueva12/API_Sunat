<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Closure;


interface GestorTransacciones
{
    /**
     * @template T
     *
     * @param  Closure(): T  $operacion
     * @return T
     */
    public function ejecutar(Closure $operacion): mixed;
}
