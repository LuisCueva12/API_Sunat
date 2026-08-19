<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;

final class SerieEmpresa
{
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly TipoComprobante $tipoComprobante,
        private readonly Serie $serie,
        private bool $activa,
    ) {}

    public static function registrar(
        string $id,
        string $empresaId,
        TipoComprobante $tipoComprobante,
        Serie $serie,
    ): self {
        return new self($id, $empresaId, $tipoComprobante, $serie, true);
    }

    public static function reconstituir(
        string $id,
        string $empresaId,
        TipoComprobante $tipoComprobante,
        Serie $serie,
        bool $activa,
    ): self {
        return new self($id, $empresaId, $tipoComprobante, $serie, $activa);
    }

    public function activar(): void
    {
        $this->activa = true;
    }

    public function desactivar(): void
    {
        $this->activa = false;
    }

    public function estaActiva(): bool
    {
        return $this->activa;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function empresaId(): string
    {
        return $this->empresaId;
    }

    public function tipoComprobante(): TipoComprobante
    {
        return $this->tipoComprobante;
    }

    public function serie(): Serie
    {
        return $this->serie;
    }
}
