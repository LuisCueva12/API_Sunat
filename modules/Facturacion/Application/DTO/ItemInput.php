<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\DTO;

final class ItemInput
{
    public function __construct(
        public readonly string $descripcion,
        public readonly string $unidadMedida,
        public readonly float $cantidad,
        public readonly string $valorUnitario,
        public readonly string $tipoAfectacionIgv,
        public readonly ?string $codigoProducto = null,
        public readonly ?string $descuento = null,
    ) {}
}
