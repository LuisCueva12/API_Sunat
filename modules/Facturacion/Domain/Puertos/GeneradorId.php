<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

interface GeneradorId
{
    public function nuevo(): string;
}
