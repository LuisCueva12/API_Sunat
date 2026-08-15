<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Sunat\Greenter;

use Greenter\Ws\Services\SunatEndpoints;
use InvalidArgumentException;

final class ConfiguracionSunat
{
    public static function endpoint(string $entorno): string
    {
        return match ($entorno) {
            'BETA' => SunatEndpoints::FE_BETA,
            'PRODUCCION' => SunatEndpoints::FE_PRODUCCION,
            default => throw new InvalidArgumentException("Entorno SUNAT desconocido: '{$entorno}'."),
        };
    }
}
