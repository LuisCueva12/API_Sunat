<?php

declare(strict_types=1);

namespace App\Services\ApiKeys;

final class GeneradorApiKey
{
    private const PREFIJO_BASE = 'fe_live_';

    public function generar(): ResultadoGeneracionApiKey
    {
        $aleatorio = $this->cadenaAleatoria(32);
        $keyCompleta = self::PREFIJO_BASE.$aleatorio;

        return new ResultadoGeneracionApiKey(
            keyCompleta: $keyCompleta,
            prefijo: self::PREFIJO_BASE.substr($aleatorio, 0, 8),
            hash: $this->hash($keyCompleta),
        );
    }

    public function hash(string $keyCompleta): string
    {
        return hash('sha256', $keyCompleta);
    }

    private function cadenaAleatoria(int $longitud): string
    {
        $bytesNecesarios = (int) ceil($longitud * 3 / 4) + 3;
        $base64 = base64_encode(random_bytes($bytesNecesarios));
        $limpio = strtr(rtrim($base64, '='), '+/', 'ab');

        return substr($limpio, 0, $longitud);
    }
}
