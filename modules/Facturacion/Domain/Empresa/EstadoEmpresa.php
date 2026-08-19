<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

enum EstadoEmpresa: string
{
    case Activa = 'ACTIVA';
    case Inactiva = 'INACTIVA';
    case Suspendida = 'SUSPENDIDA';
}
