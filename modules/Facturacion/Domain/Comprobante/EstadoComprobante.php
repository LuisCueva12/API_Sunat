<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Comprobante;

/**
 * Estado de negocio, no progreso técnico. Los pasos intermedios del
 * pipeline (XML generado, firmado, enviado) son eventos append-only en
 * eventos_comprobante, no valores de este enum — ver docs/02_DOMINIO.md.
 */
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

    // Una vez RECHAZADO, el correlativo queda quemado: no hay transición de
    // vuelta a PROCESANDO. Solo ERROR (fallo técnico previo a una respuesta
    // definitiva de SUNAT) admite reintento.
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
