<?php

declare(strict_types=1);

namespace App\Services\ApiKeys;

/**
 * $keyCompleta solo existe en este objeto transitorio — nunca se persiste,
 * se muestra al usuario una única vez al crearla.
 */
final class ResultadoGeneracionApiKey
{
    public function __construct(
        public readonly string $keyCompleta,
        public readonly string $prefijo,
        public readonly string $hash,
    ) {}
}
