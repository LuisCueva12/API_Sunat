<?php

declare(strict_types=1);

use Modules\Clientes\Domain\Cliente;
use Modules\Clientes\Domain\Excepciones\ClienteInvalidoException;
use Modules\Clientes\Domain\TipoDocumentoCliente;

it('se registra con los datos provistos, recortando espacios', function () {
    $cliente = Cliente::registrar(
        id: 'cliente-1',
        empresaId: 'empresa-1',
        tipoDocumento: TipoDocumentoCliente::Ruc,
        numeroDocumento: '20100070970',
        razonSocial: '  Cliente SAC  ',
        direccion: '  Av. Prueba 123  ',
        email: '  contacto@cliente.pe  ',
    );

    expect($cliente->razonSocial())->toBe('Cliente SAC')
        ->and($cliente->direccion())->toBe('Av. Prueba 123')
        ->and($cliente->email())->toBe('contacto@cliente.pe')
        ->and($cliente->tipoDocumento())->toBe(TipoDocumentoCliente::Ruc);
});

it('normaliza dirección y email vacíos a null', function () {
    $cliente = Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Dni, '12345678', 'Nombre', '   ', '');

    expect($cliente->direccion())->toBeNull()
        ->and($cliente->email())->toBeNull();
});

it('rechaza un número de documento vacío', function () {
    Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Dni, '   ', 'Nombre', null, null);
})->throws(ClienteInvalidoException::class);

it('rechaza una razón social vacía', function () {
    Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Dni, '12345678', '   ', null, null);
})->throws(ClienteInvalidoException::class);

it('actualiza razón social, dirección y email', function () {
    $cliente = Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Dni, '12345678', 'Nombre original', null, null);

    $cliente->actualizar('Nombre actualizado', 'Nueva dirección', 'nuevo@correo.pe');

    expect($cliente->razonSocial())->toBe('Nombre actualizado')
        ->and($cliente->direccion())->toBe('Nueva dirección')
        ->and($cliente->email())->toBe('nuevo@correo.pe');
});

it('rechaza actualizar con una razón social vacía', function () {
    $cliente = Cliente::registrar('cliente-1', 'empresa-1', TipoDocumentoCliente::Dni, '12345678', 'Nombre', null, null);

    $cliente->actualizar('   ', null, null);
})->throws(ClienteInvalidoException::class);
