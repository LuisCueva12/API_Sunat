<?php

declare(strict_types=1);

namespace Modules\Clientes\Domain;

// Mismo catálogo SUNAT que Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad
// (0 sin documento, 1 DNI, 4 carnet de extranjería, 6 RUC, 7 pasaporte) —
// duplicado a propósito, no importado: cada módulo define su propio
// vocabulario, deptrac impide que un módulo dependa del Domain de otro.
enum TipoDocumentoCliente: string
{
    case SinDocumento = '0';
    case Dni = '1';
    case CarnetExtranjeria = '4';
    case Ruc = '6';
    case Pasaporte = '7';
}
