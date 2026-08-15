<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

final class DocumentoIdentidad
{
    private readonly string $numero;

    public function __construct(
        private readonly TipoDocumentoIdentidad $tipo,
        string $numero,
    ) {
        $numero = trim($numero);

        match ($tipo) {
            TipoDocumentoIdentidad::SinDocumento => null,
            TipoDocumentoIdentidad::Dni => self::validarDni($numero),
            TipoDocumentoIdentidad::Ruc => new Ruc($numero), // valida formato internamente
            TipoDocumentoIdentidad::CarnetExtranjeria,
            TipoDocumentoIdentidad::Pasaporte => self::validarNoVacio($numero),
        };

        $this->numero = $numero;
    }

    private static function validarDni(string $numero): void
    {
        if (! preg_match('/^\d{8}$/', $numero)) {
            throw new InvalidArgumentException("El DNI '{$numero}' debe tener 8 dígitos numéricos.");
        }
    }

    private static function validarNoVacio(string $numero): void
    {
        if ($numero === '') {
            throw new InvalidArgumentException('El número de documento es obligatorio para este tipo.');
        }
    }

    public function tipo(): TipoDocumentoIdentidad
    {
        return $this->tipo;
    }

    public function numero(): string
    {
        return $this->numero;
    }

    public function equals(self $otro): bool
    {
        return $this->tipo === $otro->tipo && $this->numero === $otro->numero;
    }
}
