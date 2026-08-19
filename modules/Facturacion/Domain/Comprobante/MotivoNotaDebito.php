<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

// Catálogo 10 SUNAT — Códigos de tipo de nota de débito electrónica.
// Fuente: Resolución de Superintendencia N.° 193-2020/SUNAT, anexo 3.
enum MotivoNotaDebito: string
{
    case InteresesPorMora = '01';
    case AumentoValor = '02';
    case PenalidadesOtrosConceptos = '03';
    case AjustesExportacion = '11';
    case AjustesIvap = '12';
}
