<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Domain\Comprobante\TipoComprobante;

final class EmitirBoleta extends EmitirComprobanteBase
{
    protected function tipo(): TipoComprobante
    {
        return TipoComprobante::Boleta;
    }
}
