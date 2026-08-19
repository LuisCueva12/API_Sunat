<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

enum EntornoSunat: string
{
    case Beta = 'BETA';
    case Produccion = 'PRODUCCION';
}
