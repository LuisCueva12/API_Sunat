<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

use Modules\Facturacion\Domain\ValueObjects\Dinero;

final class Tributo
{
    public function __construct(
        private readonly string $tipoTributo,
        private readonly ?string $codigo,
        private readonly Dinero $baseImponible,
        private readonly Dinero $monto,
    ) {}

    public function tipoTributo(): string
    {
        return $this->tipoTributo;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function baseImponible(): Dinero
    {
        return $this->baseImponible;
    }

    public function monto(): Dinero
    {
        return $this->monto;
    }
}
