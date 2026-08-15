<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

final class Serie
{
    private readonly string $valor;

    // 4 caracteres alfanuméricos en mayúscula: cubre tanto "F001"/"B001"
    // como el formato histórico "FC01"/"BC01" de notas — el formato exacto
    // vigente por tipo de comprobante se confirma en Fase 5 (ver
    // docs/05_SUNAT.md) y se aplica en el validador específico de cada tipo,
    // no aquí.
    public function __construct(string $valor)
    {
        $valor = strtoupper(trim($valor));

        if (! preg_match('/^[A-Z0-9]{4}$/', $valor)) {
            throw new InvalidArgumentException("La serie '{$valor}' no tiene un formato válido (4 caracteres, ej. F001).");
        }

        $this->valor = $valor;
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function equals(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
