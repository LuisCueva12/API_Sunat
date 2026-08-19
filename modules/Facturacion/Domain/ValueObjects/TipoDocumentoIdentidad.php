<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

enum TipoDocumentoIdentidad: string
{
    case SinDocumento = '0';
    case Dni = '1';
    case CarnetExtranjeria = '4';
    case Ruc = '6';
    case Pasaporte = '7';
}
