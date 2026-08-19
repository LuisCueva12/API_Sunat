<?php

declare(strict_types=1);

namespace Modules\Clientes\Domain;

use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;

final class Cliente
{
    private function __construct(
        private readonly string $id,
        private readonly string $empresaId,
        private readonly TipoDocumentoCliente $tipoDocumento,
        private readonly string $numeroDocumento,
        private string $razonSocial,
        private ?string $direccion,
        private ?string $email,
    ) {}

    public static function registrar(
        string $id,
        string $empresaId,
        TipoDocumentoCliente $tipoDocumento,
        string $numeroDocumento,
        string $razonSocial,
        ?string $direccion,
        ?string $email,
    ): self {
        $numeroDocumento = trim($numeroDocumento);

        if ($numeroDocumento === '') {
            throw new ClienteInvalidoException('El número de documento es obligatorio.');
        }

        if (trim($razonSocial) === '') {
            throw new ClienteInvalidoException('La razón social o nombre del cliente es obligatorio.');
        }

        return new self($id, $empresaId, $tipoDocumento, $numeroDocumento, trim($razonSocial), self::normalizar($direccion), self::normalizar($email));
    }

    public static function reconstituir(
        string $id,
        string $empresaId,
        TipoDocumentoCliente $tipoDocumento,
        string $numeroDocumento,
        string $razonSocial,
        ?string $direccion,
        ?string $email,
    ): self {
        return new self($id, $empresaId, $tipoDocumento, $numeroDocumento, $razonSocial, $direccion, $email);
    }

    public function actualizar(string $razonSocial, ?string $direccion, ?string $email): void
    {
        if (trim($razonSocial) === '') {
            throw new ClienteInvalidoException('La razón social o nombre del cliente es obligatorio.');
        }

        $this->razonSocial = trim($razonSocial);
        $this->direccion = self::normalizar($direccion);
        $this->email = self::normalizar($email);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function empresaId(): string
    {
        return $this->empresaId;
    }

    public function tipoDocumento(): TipoDocumentoCliente
    {
        return $this->tipoDocumento;
    }

    public function numeroDocumento(): string
    {
        return $this->numeroDocumento;
    }

    public function razonSocial(): string
    {
        return $this->razonSocial;
    }

    public function direccion(): ?string
    {
        return $this->direccion;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    private static function normalizar(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        return trim($valor);
    }
}
