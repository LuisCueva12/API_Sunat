<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

final class NumeroComprobante
{
    public function __construct(
        private readonly Serie $serie,
        private readonly int $correlativo,
    ) {
        if ($correlativo < 1) {
            throw new InvalidArgumentException('El correlativo debe ser mayor a cero.');
        }
    }

    public function serie(): Serie
    {
        return $this->serie;
    }

    public function correlativo(): int
    {
        return $this->correlativo;
    }

    public function equals(self $otro): bool
    {
        return $this->serie->equals($otro->serie) && $this->correlativo === $otro->correlativo;
    }

    public function __toString(): string
    {
        return sprintf('%s-%08d', $this->serie->valor(), $this->correlativo);
    }
}
