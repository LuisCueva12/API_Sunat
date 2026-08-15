<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Validacion;

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;

interface ValidadorComprobante
{
    /**
     * @throws ComprobanteInvalidoException
     */
    public function validar(Comprobante $comprobante): void;
}
