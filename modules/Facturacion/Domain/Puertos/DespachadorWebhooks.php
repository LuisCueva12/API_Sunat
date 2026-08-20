<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\Comprobante;

interface DespachadorWebhooks
{
    public function despacharEventoTerminal(Comprobante $comprobante): void;
}
