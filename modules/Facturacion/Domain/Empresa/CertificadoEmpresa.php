<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\CertificadoInvalidoException;

final class CertificadoEmpresa
{
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly ?string $alias,
        private readonly string $contenidoPem,
        private readonly string $passwordCertificado,
        private readonly string $huellaSha256,
        private readonly ?DateTimeImmutable $fechaEmision,
        private readonly DateTimeImmutable $fechaExpiracion,
        private EstadoCertificado $estado,
    ) {}

    public static function registrar(
        string $id,
        string $empresaId,
        ?string $alias,
        string $contenidoPem,
        string $passwordCertificado,
        string $huellaSha256,
        ?DateTimeImmutable $fechaEmision,
        DateTimeImmutable $fechaExpiracion,
    ): self {
        if ($fechaExpiracion <= new DateTimeImmutable) {
            throw new CertificadoInvalidoException('El certificado ya está vencido; no puede registrarse como activo.');
        }

        return new self(
            id: $id,
            empresaId: $empresaId,
            alias: $alias,
            contenidoPem: $contenidoPem,
            passwordCertificado: $passwordCertificado,
            huellaSha256: $huellaSha256,
            fechaEmision: $fechaEmision,
            fechaExpiracion: $fechaExpiracion,
            estado: EstadoCertificado::Activo,
        );
    }

    public static function reconstituir(
        string $id,
        string $empresaId,
        ?string $alias,
        string $contenidoPem,
        string $passwordCertificado,
        string $huellaSha256,
        ?DateTimeImmutable $fechaEmision,
        DateTimeImmutable $fechaExpiracion,
        EstadoCertificado $estado,
    ): self {
        return new self($id, $empresaId, $alias, $contenidoPem, $passwordCertificado, $huellaSha256, $fechaEmision, $fechaExpiracion, $estado);
    }

    public function reemplazar(): void
    {
        if ($this->estado !== EstadoCertificado::Activo) {
            throw new CertificadoInvalidoException('Solo un certificado activo puede marcarse como reemplazado.');
        }

        $this->estado = EstadoCertificado::Reemplazado;
    }

    public function revocar(): void
    {
        $this->estado = EstadoCertificado::Revocado;
    }

    public function estaVigente(): bool
    {
        return $this->estado === EstadoCertificado::Activo
            && $this->fechaExpiracion > new DateTimeImmutable;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function empresaId(): string
    {
        return $this->empresaId;
    }

    public function alias(): ?string
    {
        return $this->alias;
    }

    public function contenidoPem(): string
    {
        return $this->contenidoPem;
    }

    public function passwordCertificado(): string
    {
        return $this->passwordCertificado;
    }

    public function huellaSha256(): string
    {
        return $this->huellaSha256;
    }

    public function fechaEmision(): ?DateTimeImmutable
    {
        return $this->fechaEmision;
    }

    public function fechaExpiracion(): DateTimeImmutable
    {
        return $this->fechaExpiracion;
    }

    public function estado(): EstadoCertificado
    {
        return $this->estado;
    }
}
