<?php

declare(strict_types=1);

namespace Modules\Clientes\Application\CasosDeUso;

use Modules\Clientes\Application\DTO\ActualizarClienteInput;
use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;

final class ActualizarCliente
{
    public function __construct(
        private readonly RepositorioCliente $repositorio,
    ) {}

    public function ejecutar(ActualizarClienteInput $input): Cliente
    {
        $cliente = $this->repositorio->buscarPorId($input->empresaId, $input->clienteId);

        if ($cliente === null) {
            throw new ClienteInvalidoException("No existe el cliente {$input->clienteId} para esta empresa.");
        }

        $cliente->actualizar($input->razonSocial, $input->direccion, $input->email);

        $this->repositorio->guardar($cliente);

        return $cliente;
    }
}
