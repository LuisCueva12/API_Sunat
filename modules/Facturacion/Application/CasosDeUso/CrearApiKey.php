<?php

declare(strict_types=1);

namespace Modules\Facturacion\Application\CasosDeUso;

use Modules\Facturacion\Application\DTO\CrearApiKeyInput;
use Modules\Facturacion\Application\DTO\ResultadoCrearApiKey;
use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorClaveApi;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioApiKey;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;

final class CrearApiKey
{
    public function __construct(
        private readonly RepositorioApiKey $repositorio,
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly GeneradorId $generadorId,
        private readonly GeneradorClaveApi $generadorClave,
    ) {}

    public function ejecutar(CrearApiKeyInput $input): ResultadoCrearApiKey
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        $clave = $this->generadorClave->generar();

        $apiKey = ApiKeyEmpresa::registrar(
            id: $this->generadorId->nuevo(),
            empresaId: $input->empresaId,
            nombre: $input->nombre,
            prefijo: $clave->prefijo,
            hash: $clave->hash,
            scopes: $input->scopes,
            expiraEn: $input->expiraEn,
        );

        $this->repositorio->guardar($apiKey);

        return new ResultadoCrearApiKey($apiKey, $clave->claveCompleta);
    }
}
