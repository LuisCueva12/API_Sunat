<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Validacion;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

final class ValidadorBoleta implements ValidadorComprobante
{
    public function validar(Comprobante $comprobante): void
    {
        if ($comprobante->tipo() !== TipoComprobante::Boleta) {
            throw new ComprobanteInvalidoException('ValidadorBoleta solo aplica a comprobantes de tipo BOLETA.');
        }

        if ($comprobante->receptorDocumento()->tipo() === TipoDocumentoIdentidad::Ruc) {
            throw new ComprobanteInvalidoException('Una boleta no admite un receptor identificado con RUC; use factura.');
        }

        if (count($comprobante->items()) === 0) {
            throw new ComprobanteInvalidoException('Una boleta debe tener al menos un item.');
        }

        $totales = $comprobante->totales();

        if ($totales === null || $totales->total->esCero() || $totales->total->esNegativo()) {
            throw new ComprobanteInvalidoException('El total de la boleta debe ser mayor a cero.');
        }
    }
}
