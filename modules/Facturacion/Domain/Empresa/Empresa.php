<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

final class Empresa
{
    private function __construct(
        private readonly string $id,
        private readonly Ruc $ruc,
        private string $razonSocial,
        private ?string $nombreComercial,
        private EstadoEmpresa $estado,
    ) {}

    public static function registrar(
        string $id,
        Ruc $ruc,
        string $razonSocial,
        ?string $nombreComercial = null,
    ): self {
        if (trim($razonSocial) === '') {
            throw new EmpresaInvalidaException('La razón social es obligatoria.');
        }

        return new self(
            id: $id,
            ruc: $ruc,
            razonSocial: $razonSocial,
            nombreComercial: $nombreComercial,
            estado: EstadoEmpresa::Activa,
        );
    }

    public static function reconstituir(
        string $id,
        Ruc $ruc,
        string $razonSocial,
        ?string $nombreComercial,
        EstadoEmpresa $estado,
    ): self {
        return new self($id, $ruc, $razonSocial, $nombreComercial, $estado);
    }

    public function activar(): void
    {
        $this->estado = EstadoEmpresa::Activa;
    }

    public function desactivar(): void
    {
        $this->estado = EstadoEmpresa::Inactiva;
    }

    public function suspender(): void
    {
        $this->estado = EstadoEmpresa::Suspendida;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoEmpresa::Activa;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function ruc(): Ruc
    {
        return $this->ruc;
    }

    public function razonSocial(): string
    {
        return $this->razonSocial;
    }

    public function nombreComercial(): ?string
    {
        return $this->nombreComercial;
    }

    public function estado(): EstadoEmpresa
    {
        return $this->estado;
    }
}
