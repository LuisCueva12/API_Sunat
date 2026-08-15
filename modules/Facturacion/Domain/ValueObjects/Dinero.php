<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Respaldado por un entero (centavos), nunca float ni bcmath — bcmath no
 * está disponible en todos los entornos y trabajar en centavos evita el
 * problema de raíz sin depender de una extensión adicional.
 */
final class Dinero
{
    private function __construct(private readonly int $centavos) {}

    public static function centavos(int $centavos): self
    {
        return new self($centavos);
    }

    public static function cero(): self
    {
        return new self(0);
    }

    // Parseo por string, nunca por (float), para no perder precisión decimal
    // al convertir "125.50" en centavos.
    public static function desde(string $monto): self
    {
        $monto = trim($monto);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $monto, $m)) {
            throw new InvalidArgumentException("Monto inválido: '{$monto}'.");
        }

        $negativo = $m[1] === '-';
        $entero = (int) $m[2];
        $decimal = (int) str_pad($m[3] ?? '0', 2, '0');
        $centavos = $entero * 100 + $decimal;

        return new self($negativo ? -$centavos : $centavos);
    }

    public function sumar(self $otro): self
    {
        return new self($this->centavos + $otro->centavos);
    }

    public function restar(self $otro): self
    {
        return new self($this->centavos - $otro->centavos);
    }

    // Redondeo half-up sobre centavos — ver docs/05_SUNAT.md para la regla
    // de redondeo tributario exacta pendiente de verificar.
    public function multiplicarPor(float $factor): self
    {
        return new self((int) round($this->centavos * $factor));
    }

    public function esNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function esCero(): bool
    {
        return $this->centavos === 0;
    }

    public function mayorQue(self $otro): bool
    {
        return $this->centavos > $otro->centavos;
    }

    public function centavosComoEntero(): int
    {
        return $this->centavos;
    }

    public function comoString(): string
    {
        return number_format($this->centavos / 100, 2, '.', '');
    }

    public function equals(self $otro): bool
    {
        return $this->centavos === $otro->centavos;
    }

    public function __toString(): string
    {
        return $this->comoString();
    }
}
