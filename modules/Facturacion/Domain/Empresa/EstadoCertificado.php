<?php

declare(strict_types=1);

namespace Modules\Facturacion\Domain\Empresa;

enum EstadoCertificado: string
{
    case Activo = 'ACTIVO';
    case Vencido = 'VENCIDO';
    case Revocado = 'REVOCADO';
    case Reemplazado = 'REEMPLAZADO';
}
