<?php

declare(strict_types=1);

namespace Modules\Clientes\Application\CasosDeUso;

use Modules\Clientes\Application\DTO\CrearClienteInput;
use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;

final class CrearCliente
{
    public function __construct(
        private readonly RepositorioEmpresa $repositorioEmpresa,
        private readonly RepositorioCliente $repositorio,
        private readonly GeneradorId $generadorId,
    ) {}

    public function ejecutar(CrearClienteInput $input): Cliente
    {
        $empresa = $this->repositorioEmpresa->buscarPorId($input->empresaId);

        if ($empresa === null) {
            throw new EmpresaInvalidaException("No existe la empresa {$input->empresaId}.");
        }

        if (! $empresa->estaActiva()) {
            throw new EmpresaInvalidaException("La empresa {$input->empresaId} no está activa.");
        }

        $tipoDocumento = TipoDocumentoCliente::from($input->tipoDocumento);

        if ($this->repositorio->existe($input->empresaId, $tipoDocumento, $input->numeroDocumento)) {
            throw new ClienteInvalidoException(
                "Ya existe un cliente con el documento {$input->numeroDocumento} para esta empresa.",
            );
        }

        $cliente = Cliente::registrar(
            id: $this->generadorId->nuevo(),
            empresaId: $input->empresaId,
            tipoDocumento: $tipoDocumento,
            numeroDocumento: $input->numeroDocumento,
            razonSocial: $input->razonSocial,
            direccion: $input->direccion,
            email: $input->email,
        );

        $this->repositorio->guardar($cliente);

        return $cliente;
    }
}
