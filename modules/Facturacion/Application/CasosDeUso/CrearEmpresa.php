<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearEmpresaInput;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

final class CrearEmpresa
{
    public function __construct(
        private readonly RepositorioEmpresa $repositorio,
        private readonly GeneradorId $generadorId,
    ) {}

    public function ejecutar(CrearEmpresaInput $input): Empresa
    {
        $ruc = new Ruc($input->ruc);

        if ($this->repositorio->buscarPorRuc($ruc->valor()) !== null) {
            throw new EmpresaInvalidaException("Ya existe una empresa registrada con el RUC {$ruc->valor()}.");
        }

        $empresa = Empresa::registrar(
            id: $this->generadorId->nuevo(),
            ruc: $ruc,
            razonSocial: $input->razonSocial,
            nombreComercial: $input->nombreComercial,
        );

        $this->repositorio->guardar($empresa);

        return $empresa;
    }
}
