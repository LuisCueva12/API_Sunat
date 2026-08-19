<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

use DateTimeImmutable;
use Modules\Facturacion\Domain\Excepciones\ApiKeyInvalidaException;

final class ApiKeyEmpresa
{
    /** @var array<int, string> */
    public const ESCOPOS_VALIDOS = [
        'comprobantes:crear',
        'comprobantes:leer',
        'comprobantes:reintentar',
    ];

    /**
     * @param  array<int, string>  $scopes
     */
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly string $nombre,
        private readonly string $prefijo,
        private readonly string $hash,
        private readonly array $scopes,
        private readonly ?DateTimeImmutable $expiraEn,
        private EstadoApiKey $estado,
    ) {}

    /**
     * @param  array<int, string>  $scopes
     */
    public static function registrar(
        string $id,
        string $empresaId,
        string $nombre,
        string $prefijo,
        string $hash,
        array $scopes,
        ?DateTimeImmutable $expiraEn,
    ): self {
        if (trim($nombre) === '') {
            throw new ApiKeyInvalidaException('El nombre de la API Key es obligatorio.');
        }

        self::validarScopes($scopes);

        return new self($id, $empresaId, $nombre, $prefijo, $hash, $scopes, $expiraEn, EstadoApiKey::Activa);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public static function reconstituir(
        string $id,
        string $empresaId,
        string $nombre,
        string $prefijo,
        string $hash,
        array $scopes,
        ?DateTimeImmutable $expiraEn,
        EstadoApiKey $estado,
    ): self {
        return new self($id, $empresaId, $nombre, $prefijo, $hash, $scopes, $expiraEn, $estado);
    }

    public function revocar(): void
    {
        $this->estado = EstadoApiKey::Revocada;
    }

    public function estaVigente(): bool
    {
        if ($this->estado !== EstadoApiKey::Activa) {
            return false;
        }

        return $this->expiraEn === null || $this->expiraEn > new DateTimeImmutable;
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

    public function prefijo(): string
    {
        return $this->prefijo;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    /**
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }

    public function expiraEn(): ?DateTimeImmutable
    {
        return $this->expiraEn;
    }

    public function estado(): EstadoApiKey
    {
        return $this->estado;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private static function validarScopes(array $scopes): void
    {
        if ($scopes === []) {
            throw new ApiKeyInvalidaException('La API Key debe tener al menos un scope.');
        }

        foreach ($scopes as $scope) {
            if (! in_array($scope, self::ESCOPOS_VALIDOS, true)) {
                throw new ApiKeyInvalidaException("Scope desconocido: '{$scope}'.");
            }
        }
    }
}
