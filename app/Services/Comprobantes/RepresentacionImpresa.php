<?php

declare(strict_types=1);

namespace App\Services\Comprobantes;

final readonly class RepresentacionImpresa
{
    public function __construct(
        public string $nombreArchivo,
        public string $contenido,
        public string $rutaPrivada,
    ) {}
}
