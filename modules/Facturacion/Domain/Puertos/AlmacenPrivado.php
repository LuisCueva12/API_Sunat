<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

interface AlmacenPrivado
{
    public function guardar(string $ruta, string $contenido): void;

    public function leer(string $ruta): string;
}
