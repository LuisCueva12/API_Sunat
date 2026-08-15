<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

enum TipoComprobante: string
{
    case Factura = 'FACTURA';
    case Boleta = 'BOLETA';
    case NotaCredito = 'NOTA_CREDITO';
    case NotaDebito = 'NOTA_DEBITO';

    public function requiereReferencia(): bool
    {
        return match ($this) {
            self::NotaCredito, self::NotaDebito => true,
            default => false,
        };
    }
}
