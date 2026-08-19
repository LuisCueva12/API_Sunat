<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

enum EstadoComprobante: string
{
    case Registrado = 'REGISTRADO';
    case Procesando = 'PROCESANDO';
    case Aceptado = 'ACEPTADO';
    case AceptadoConObservaciones = 'ACEPTADO_CON_OBSERVACIONES';
    case Rechazado = 'RECHAZADO';
    case Error = 'ERROR';

    public function esTerminal(): bool
    {
        return match ($this) {
            self::Aceptado, self::AceptadoConObservaciones, self::Rechazado => true,
            default => false,
        };
    }

    public function puedeTransicionarA(self $destino): bool
    {
        return match ($this) {
            self::Registrado => $destino === self::Procesando,
            self::Procesando => in_array($destino, [
                self::Aceptado,
                self::AceptadoConObservaciones,
                self::Rechazado,
                self::Error,
            ], true),
            self::Error => $destino === self::Procesando,
            self::Aceptado, self::AceptadoConObservaciones, self::Rechazado => false,
        };
    }
}
