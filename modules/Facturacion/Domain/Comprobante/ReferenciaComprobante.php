<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

use InvalidArgumentException;

final class ReferenciaComprobante
{
    public function __construct(
        private readonly string $comprobanteId,
        private readonly string $codigoMotivo,
        private readonly string $descripcionMotivo,
    ) {
        if (trim($descripcionMotivo) === '') {
            throw new InvalidArgumentException('El motivo de la nota es obligatorio.');
        }
    }

    public function comprobanteId(): string
    {
        return $this->comprobanteId;
    }

    public function codigoMotivo(): string
    {
        return $this->codigoMotivo;
    }

    public function descripcionMotivo(): string
    {
        return $this->descripcionMotivo;
    }
}
