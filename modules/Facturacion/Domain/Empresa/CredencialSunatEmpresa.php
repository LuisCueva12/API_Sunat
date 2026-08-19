<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use Modules\Facturacion\Domain\Excepciones\CredencialSunatInvalidaException;

final class CredencialSunatEmpresa
{
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly EntornoSunat $entorno,
        private string $usuarioSol,
        private string $claveSol,
        private bool $activa,
    ) {}

    public static function registrar(
        string $id,
        string $empresaId,
        EntornoSunat $entorno,
        string $usuarioSol,
        string $claveSol,
    ): self {
        self::validar($usuarioSol, $claveSol);

        return new self($id, $empresaId, $entorno, $usuarioSol, $claveSol, true);
    }

    public static function reconstituir(
        string $id,
        string $empresaId,
        EntornoSunat $entorno,
        string $usuarioSol,
        string $claveSol,
        bool $activa,
    ): self {
        return new self($id, $empresaId, $entorno, $usuarioSol, $claveSol, $activa);
    }

    public function rotar(string $usuarioSol, string $claveSol): void
    {
        self::validar($usuarioSol, $claveSol);

        $this->usuarioSol = $usuarioSol;
        $this->claveSol = $claveSol;
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

    public function entorno(): EntornoSunat
    {
        return $this->entorno;
    }

    public function usuarioSol(): string
    {
        return $this->usuarioSol;
    }

    public function claveSol(): string
    {
        return $this->claveSol;
    }

    private static function validar(string $usuarioSol, string $claveSol): void
    {
        if (trim($usuarioSol) === '') {
            throw new CredencialSunatInvalidaException('El usuario SOL es obligatorio.');
        }

        if (trim($claveSol) === '') {
            throw new CredencialSunatInvalidaException('La clave SOL es obligatoria.');
        }
    }
}
