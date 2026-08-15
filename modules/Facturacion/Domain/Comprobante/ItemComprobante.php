<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

use InvalidArgumentException;
use Modules\Facturacion\Domain\ValueObjects\Dinero;

final class ItemComprobante
{
    public function __construct(
        private readonly int $numeroOrden,
        private readonly string $descripcion,
        private readonly string $unidadMedida,
        private readonly float $cantidad,
        private readonly Dinero $valorUnitario,
        private readonly Dinero $precioUnitario,
        private readonly string $tipoAfectacionIgv,
        private readonly Dinero $montoIgv,
        private readonly Dinero $montoValorVenta,
        private readonly Dinero $descuento,
        private readonly ?string $codigoProducto = null,
    ) {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        if (trim($descripcion) === '') {
            throw new InvalidArgumentException('La descripción del item es obligatoria.');
        }
    }

    public function numeroOrden(): int
    {
        return $this->numeroOrden;
    }

    public function descripcion(): string
    {
        return $this->descripcion;
    }

    public function unidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function valorUnitario(): Dinero
    {
        return $this->valorUnitario;
    }

    public function precioUnitario(): Dinero
    {
        return $this->precioUnitario;
    }

    public function tipoAfectacionIgv(): string
    {
        return $this->tipoAfectacionIgv;
    }

    public function montoIgv(): Dinero
    {
        return $this->montoIgv;
    }

    public function montoValorVenta(): Dinero
    {
        return $this->montoValorVenta;
    }

    public function descuento(): Dinero
    {
        return $this->descuento;
    }

    public function codigoProducto(): ?string
    {
        return $this->codigoProducto;
    }
}
