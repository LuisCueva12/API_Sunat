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
}
