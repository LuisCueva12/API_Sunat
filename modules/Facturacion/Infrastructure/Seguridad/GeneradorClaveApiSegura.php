<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Seguridad;

use Modules\Facturacion\Domain\Empresa\ResultadoClaveApi;
use Modules\Facturacion\Domain\Puertos\GeneradorClaveApi;

final class GeneradorClaveApiSegura implements GeneradorClaveApi
{
    private const PREFIJO_BASE = 'fe_live_';

    public function generar(): ResultadoClaveApi
    {
        $aleatorio = $this->cadenaAleatoria(32);
        $claveCompleta = self::PREFIJO_BASE.$aleatorio;

        return new ResultadoClaveApi(
            claveCompleta: $claveCompleta,
            prefijo: self::PREFIJO_BASE.substr($aleatorio, 0, 8),
            hash: $this->hash($claveCompleta),
        );
    }

    public function hash(string $claveCompleta): string
    {
        return hash('sha256', $claveCompleta);
    }

    private function cadenaAleatoria(int $longitud): string
    {
        $bytesNecesarios = (int) ceil($longitud * 3 / 4) + 3;
        $base64 = base64_encode(random_bytes($bytesNecesarios));
        $limpio = strtr(rtrim($base64, '='), '+/', 'ab');

        return substr($limpio, 0, $longitud);
    }
}
