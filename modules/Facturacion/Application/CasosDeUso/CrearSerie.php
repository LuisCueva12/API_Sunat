<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearSerieInput;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\SerieEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioSerie;
use Modules\Facturacion\Domain\ValueObjects\Serie;

final class CrearSerie
{
    public function __construct(
        private readonly RepositorioSerie $repositorio,
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly GeneradorId $generadorId,
    ) {}

    public function ejecutar(CrearSerieInput $input): SerieEmpresa
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        $tipo = TipoComprobante::from($input->tipoComprobante);
        $serie = new Serie($input->serie);

        if ($this->repositorio->existe($input->empresaId, $tipo, $serie)) {
            throw new SerieInvalidaException(
                "Ya existe la serie {$serie->valor()} de tipo {$tipo->value} para esta empresa."
            );
        }

        $serieEmpresa = SerieEmpresa::registrar(
            id: $this->generadorId->nuevo(),
            empresaId: $input->empresaId,
            tipoComprobante: $tipo,
            serie: $serie,
        );

        $this->repositorio->guardar($serieEmpresa);

        return $serieEmpresa;
    }
}
