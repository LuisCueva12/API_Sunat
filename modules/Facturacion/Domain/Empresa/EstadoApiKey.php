<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

enum EstadoApiKey: string
{
    case Activa = 'ACTIVA';
    case Revocada = 'REVOCADA';
}
