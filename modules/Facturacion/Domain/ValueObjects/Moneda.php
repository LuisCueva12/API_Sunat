<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

enum Moneda: string
{
    case PEN = 'PEN';
    case USD = 'USD';
}
