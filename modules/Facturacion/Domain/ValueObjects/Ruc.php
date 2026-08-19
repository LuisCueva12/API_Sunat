<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

final class Ruc
{
    private readonly string $valor;

    public function __construct(string $valor)
    {
        $valor = trim($valor);

        if (! preg_match('/^\d{11}$/', $valor)) {
            throw new InvalidArgumentException("El RUC '{$valor}' debe tener 11 dígitos numéricos.");
        }

        if (! in_array(substr($valor, 0, 2), ['10', '15', '17', '20'], true)) {
            throw new InvalidArgumentException("El RUC '{$valor}' tiene un prefijo inválido.");
        }

        if (! self::digitoVerificadorValido($valor)) {
            throw new InvalidArgumentException("El RUC '{$valor}' tiene un dígito verificador inválido.");
        }

        $this->valor = $valor;
    }

    private static function digitoVerificadorValido(string $ruc): bool
    {
        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $ruc[$i] * $factores[$i];
        }

        $digito = 11 - ($suma % 11);
        $digito = match (true) {
            $digito === 10 => 0,
            $digito === 11 => 1,
            default => $digito,
        };

        return $digito === (int) $ruc[10];
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function esPersonaJuridica(): bool
    {
        return str_starts_with($this->valor, '20');
    }

    public function equals(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
