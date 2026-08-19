<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Puertos;

interface DespachadorProcesamiento
{
    public function despacharEnvio(string $empresaId, string $comprobanteId): void;
}
