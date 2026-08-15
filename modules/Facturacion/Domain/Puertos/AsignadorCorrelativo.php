<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;

interface AsignadorCorrelativo
{
    /**
     * Asigna el siguiente correlativo disponible para empresa+tipo+serie de
     * forma segura ante concurrencia. Nunca reutiliza un número ya asignado,
     * incluso si el comprobante correspondiente termina en ERROR.
     *
     * @throws SerieInvalidaException
     */
    public function asignar(string $empresaId, TipoComprobante $tipo, Serie $serie): NumeroComprobante;
}
