<?php

declare(strict_types=1);

use Modules\Clientes\Application\CasosDeUso\CrearCliente;
use Modules\Clientes\Application\DTO\CrearClienteInput;
use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;
use Modules\Clientes\Domain\Puertos\RepositorioCliente;
use Modules\Clientes\Domain\TipoDocumentoCliente;
use Modules\Facturacion\Domain\Empresa\Empresa;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;
use Modules\Facturacion\Domain\Puertos\GeneradorId;
use Modules\Facturacion\Domain\Puertos\RepositorioEmpresa;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

function repositorioEmpresaFalsoParaClientes(?Empresa $empresa): RepositorioEmpresa
{
    return new class($empresa) implements RepositorioEmpresa
    {
        public function __construct(private readonly ?Empresa $empresa) {}

        public function guardar(Empresa $empresa): void {}

        public function buscarPorId(string $id): ?Empresa
        {
            return $this->empresa;
        }

        public function buscarPorRuc(string $ruc): ?Empresa
        {
            return $this->empresa;
        }
    };
}

function generadorIdFalsoParaClientes(): GeneradorId
{
    return new class implements GeneradorId
    {
        public function nuevo(): string
        {
            return 'cliente-1';
        }
    };
}

function repositorioClienteFalso(bool $existe = false): RepositorioCliente
{
    return new class($existe) implements RepositorioCliente
    {
        /** @var array<int, Cliente> */
        public array $guardados = [];

        public function __construct(private readonly bool $existe) {}

        public function guardar(Cliente $cliente): void
        {
            $this->guardados[] = $cliente;
        }

        public function buscarPorId(string $empresaId, string $id): ?Cliente
        {
            return null;
        }

        public function existe(string $empresaId, TipoDocumentoCliente $tipoDocumento, string $numeroDocumento): bool
        {
            return $this->existe;
        }
    };
}

it('crea un cliente para una empresa activa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $repositorio = repositorioClienteFalso();

    $casoDeUso = new CrearCliente(repositorioEmpresaFalsoParaClientes($empresa), $repositorio, generadorIdFalsoParaClientes());

    $cliente = $casoDeUso->ejecutar(new CrearClienteInput(
        empresaId: 'empresa-1',
        tipoDocumento: '6',
        numeroDocumento: '20100070971',
        razonSocial: 'Cliente de Prueba SAC',
        email: 'contacto@cliente.pe',
    ));

    expect($cliente->id())->toBe('cliente-1')
        ->and($cliente->razonSocial())->toBe('Cliente de Prueba SAC')
        ->and($repositorio->guardados)->toHaveCount(1);
});

it('rechaza crear un cliente para una empresa inexistente', function () {
    $casoDeUso = new CrearCliente(repositorioEmpresaFalsoParaClientes(null), repositorioClienteFalso(), generadorIdFalsoParaClientes());

    $casoDeUso->ejecutar(new CrearClienteInput('empresa-1', '6', '20100070971', 'Cliente'));
})->throws(EmpresaInvalidaException::class);

it('rechaza crear un cliente duplicado para la misma empresa', function () {
    $empresa = Empresa::registrar('empresa-1', new Ruc('20100070970'), 'Empresa SAC');
    $casoDeUso = new CrearCliente(repositorioEmpresaFalsoParaClientes($empresa), repositorioClienteFalso(existe: true), generadorIdFalsoParaClientes());

    $casoDeUso->ejecutar(new CrearClienteInput('empresa-1', '6', '20100070971', 'Cliente'));
})->throws(ClienteInvalidoException::class);
