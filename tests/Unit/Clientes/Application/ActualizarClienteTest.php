<?php

declare(strict_types=1);

use Modules\Clientes\Application\CasosDeUso\ActualizarCliente;
use Modules\Clientes\Application\DTO\ActualizarClienteInput;
use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;

function repositorioClienteConRegistro(?Cliente $cliente): RepositorioCliente
{
    return new class($cliente) implements RepositorioCliente
    {
        /** @var array<int, Cliente> */
        public array $guardados = [];

        public function __construct(private readonly ?Cliente $cliente) {}

        public function guardar(Cliente $cliente): void
        {
            $this->guardados[] = $cliente;
        }

        public function buscarPorId(string $empresaId, string $id): ?Cliente
        {
            return $this->cliente;
        }

        public function buscarPorDocumento(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): ?Cliente
        {
            return null;
        }

        public function existe(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): bool
        {
            return false;
        }
    };
}

it('actualiza un cliente existente', function () {
    $cliente = Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Ruc, '20100070971', 'Nombre original', null, null);
    $repositorio = repositorioClienteConRegistro($cliente);

    $casoDeUso = new ActualizarCliente($repositorio);
    $actualizado = $casoDeUso->ejecutar(new ActualizarClienteInput('empresa-1', 'cliente-1', 'Nombre actualizado', 'Nueva dirección', null));

    expect($actualizado->razonSocial())->toBe('Nombre actualizado')
        ->and($actualizado->direccion())->toBe('Nueva dirección')
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rechaza actualizar un cliente que no existe o no pertenece a la empresa', function () {
    $casoDeUso = new ActualizarCliente(repositorioClienteConRegistro(null));

    $casoDeUso->ejecutar(new ActualizarClienteInput('empresa-1', 'cliente-1', 'Nombre', null, null));
})->throws(ClienteInvalidoException::class);
