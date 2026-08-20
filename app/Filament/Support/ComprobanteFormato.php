<?php

declare(strict_types=1);

namespace App\Filament\Support;

final class ComprobanteFormato
{
    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'FACTURA' => 'Factura',
            'BOLETA' => 'Boleta',
            'NOTA_CREDITO' => 'Nota de crédito',
            'NOTA_DEBITO' => 'Nota de débito',
            default => $tipo,
        };
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'REGISTRADO' => 'Registrado',
            'PROCESANDO' => 'Procesando',
            'ACEPTADO' => 'Aceptado',
            'ACEPTADO_CON_OBSERVACIONES' => 'Aceptado con observaciones',
            'RECHAZADO' => 'Rechazado',
            'ERROR' => 'Error',
            default => $estado,
        };
    }

    public static function colorEstado(string $estado): string
    {
        return match ($estado) {
            'ACEPTADO' => 'success',
            'ACEPTADO_CON_OBSERVACIONES' => 'warning',
            'PROCESANDO' => 'info',
            'RECHAZADO', 'ERROR' => 'danger',
            default => 'gray',
        };
    }

    public static function mensajeEstadoFacturador(string $estado): string
    {
        return match ($estado) {
            'REGISTRADO' => 'Tu comprobante fue registrado y será enviado a SUNAT en breve.',
            'PROCESANDO' => 'Estamos enviando el comprobante a SUNAT. No necesitas realizar ninguna acción.',
            'ACEPTADO' => 'El comprobante fue aceptado por SUNAT.',
            'ACEPTADO_CON_OBSERVACIONES' => 'SUNAT aceptó el comprobante con observaciones. Si necesitas ayuda, contacta a soporte.',
            'RECHAZADO' => 'SUNAT no aceptó el comprobante. Revisa los datos registrados o solicita ayuda.',
            'ERROR' => 'No pudimos completar el envío. Puedes usar Reintentar; si continúa, solicita ayuda.',
            default => 'Consulta nuevamente en unos momentos para conocer el estado del comprobante.',
        };
    }
}
