<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\IntegracionApiInvalidaException;

final class IntegracionApi
{
    /**
     * @param  array<int, string>  $scopes
     */
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly string $nombre,
        private readonly array $scopes,
        private EstadoIntegracionApi $estado,
        private readonly ?DateTimeImmutable $ultimoUsoEn,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     */
    public static function registrar(
        string $id,
        string $empresaId,
        string $nombre,
        array $scopes,
    ): self {
        if (trim($nombre) === '') {
            throw new IntegracionApiInvalidaException('El nombre de la integración es obligatorio.');
        }

        self::validarScopes($scopes);

        return new self($id, $empresaId, $nombre, $scopes, EstadoIntegracionApi::Activa, null);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public static function reconstituir(
        string $id,
        string $empresaId,
        string $nombre,
        array $scopes,
        EstadoIntegracionApi $estado,
        ?DateTimeImmutable $ultimoUsoEn,
    ): self {
        return new self($id, $empresaId, $nombre, $scopes, $estado, $ultimoUsoEn);
    }

    public function revocar(): void
    {
        $this->estado = EstadoIntegracionApi::Revocada;
    }

    public function estaVigente(): bool
    {
        return $this->estado === EstadoIntegracionApi::Activa;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function empresaId(): string
    {
        return $this->empresaId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function estado(): EstadoIntegracionApi
    {
        return $this->estado;
    }

    public function ultimoUsoEn(): ?DateTimeImmutable
    {
        return $this->ultimoUsoEn;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public static function validarScopes(array $scopes): void
    {
        if ($scopes === []) {
            throw new IntegracionApiInvalidaException('La integración debe tener al menos un scope.');
        }

        foreach ($scopes as $scope) {
            if (! in_array($scope, ScopeApi::valores(), true)) {
                throw new IntegracionApiInvalidaException("Scope desconocido: '{$scope}'.");
            }
        }
    }
}
