<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Validacion;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

final class ValidadorFactura implements ValidadorComprobante
{
    public function validar(Comprobante $comprobante): void
    {
        if ($comprobante->tipo() !== TipoComprobante::Factura) {
            throw new ComprobanteInvalidoException('ValidadorFactura solo aplica a comprobantes de tipo FACTURA.');
        }

        if ($comprobante->receptorDocumento()->tipo() !== TipoDocumentoIdentidad::Ruc) {
            throw new ComprobanteInvalidoException('Una factura requiere que el receptor se identifique con RUC.');
        }

        if (count($comprobante->items()) === 0) {
            throw new ComprobanteInvalidoException('Una factura debe tener al menos un item.');
        }

        $totales = $comprobante->totales();

        if ($totales === null || $totales->total->esCero() || $totales->total->esNegativo()) {
            throw new ComprobanteInvalidoException('El total de la factura debe ser mayor a cero.');
        }
    }
}
