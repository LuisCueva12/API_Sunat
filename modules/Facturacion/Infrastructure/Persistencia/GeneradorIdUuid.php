<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia;

use Illuminate\Support\Str;
use Modules\Facturacion\Domain\Puertos\GeneradorId;

final class GeneradorIdUuid implements GeneradorId
{
    public function nuevo(): string
    {
        return (string) Str::uuid7();
    }
}
