<?php

declare(strict_types=1);

namespace App\Services\ApiKeys;

final class ResultadoGeneracionApiKey
{
    public function __construct(
        public readonly string $keyCompleta,
        public readonly string $prefijo,
        public readonly string $hash,
    ) {}
}
