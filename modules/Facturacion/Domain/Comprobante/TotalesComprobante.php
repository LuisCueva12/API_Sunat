<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

use Modules\Facturacion\Domain\ValueObjects\Dinero;

final class TotalesComprobante
{
    public function __construct(
        public readonly Dinero $opGravada,
        public readonly Dinero $opExonerada,
        public readonly Dinero $opInafecta,
        public readonly Dinero $opGratuita,
        public readonly Dinero $totalIgv,
        public readonly Dinero $totalDescuentos,
        public readonly Dinero $total,
    ) {}
}
