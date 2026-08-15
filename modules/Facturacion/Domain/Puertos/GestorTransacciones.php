<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Closure;

/**
 * Permite que Application orqueste operaciones dentro de una transacción de
 * base de datos sin depender de Illuminate\Support\Facades\DB directamente
 * — mantiene Application tan libre de framework como Domain. Closure (no
 * callable) a propósito: es lo que espera DB::transaction() por debajo y
 * evita perder el tipo genérico de retorno en el análisis estático.
 */
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
