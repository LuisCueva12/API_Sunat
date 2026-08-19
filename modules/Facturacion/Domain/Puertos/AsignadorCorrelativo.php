<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;

interface AsignadorCorrelativo
{
    public function asignar(string $empresaId, TipoComprobante $tipo, Serie $serie): NumeroComprobante;
}
