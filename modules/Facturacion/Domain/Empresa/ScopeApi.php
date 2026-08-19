<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

enum ScopeApi: string
{
    case ComprobantesCrear = 'comprobantes:crear';
    case ComprobantesLeer = 'comprobantes:leer';
    case ComprobantesReintentar = 'comprobantes:reintentar';

    /**
     * @return array<int, string>
     */
    public static function valores(): array
    {
        return array_map(fn (self $caso): string => $caso->value, self::cases());
    }
}
