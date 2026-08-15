<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

// Catálogo 06 SUNAT (tipo de documento de identidad). Códigos según la
// tabla vigente más común — verificar contra el catálogo oficial SUNAT
// antes de Fase 5 (ver docs/05_SUNAT.md).
enum TipoDocumentoIdentidad: string
{
    case SinDocumento = '0';
    case Dni = '1';
    case CarnetExtranjeria = '4';
    case Ruc = '6';
    case Pasaporte = '7';
}
