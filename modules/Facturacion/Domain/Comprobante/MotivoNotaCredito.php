<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

// Catálogo 09 SUNAT — Códigos de tipo de nota de crédito electrónica.
// Fuente: Resolución de Superintendencia N.° 193-2020/SUNAT, anexo 3.
enum MotivoNotaCredito: string
{
    case AnulacionOperacion = '01';
    case AnulacionErrorRuc = '02';
    case CorreccionDescripcion = '03';
    case DescuentoGlobal = '04';
    case DescuentoPorItem = '05';
    case DevolucionTotal = '06';
    case DevolucionPorItem = '07';
    case Bonificacion = '08';
    case DisminucionValor = '09';
    case OtrosConceptos = '10';
    case AjustesExportacion = '11';
    case AjustesIvap = '12';
    case CorreccionMontoOFechaPago = '13';
}
