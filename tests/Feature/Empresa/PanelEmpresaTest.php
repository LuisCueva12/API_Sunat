<?php

declare(strict_types=1);

use App\Filament\Empresa\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Empresa\Resources\Clientes\Pages\ListClientes;
use App\Filament\Empresa\Resources\Comprobantes\Pages\ListComprobantes;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actuarComoUsuarioEmpresa(string $empresaId, string $email = 'cliente@example.test'): Usuario
{
    $usuario = Usuario::query()->create([
        'empresa_id' => $empresaId,
        'name' => 'Cliente',
        'email' => $email,
        'password' => 'Clave-segura-123!',
    ]);
    Filament::setCurrentPanel(Filament::getPanel('empresa'));
    test()->actingAs($usuario);

    return $usuario;
}

it('redirige a los invitados al login del panel de empresa', function () {
    $this->get('/app')->assertRedirect('/app/login');
});

it('rechaza un usuario sin empresa asignada y cierra su sesión', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Sin empresa',
        'email' => 'sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);

    $this->actingAs($usuario)->get('/app')->assertRedirect('/admin/login');
    $this->assertGuest('web');
});

it('permite a un usuario de empresa entrar a su panel', function () {
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Panel SAC',
        'estado' => 'ACTIVA',
    ]);
    actuarComoUsuarioEmpresa($empresa->id);

    $this->get('/app')->assertOk();
    $this->get('/app/comprobantes')->assertOk();
    $this->get('/app/clientes')->assertOk();
});

it('un usuario de empresa ve solo los comprobantes de su propia empresa', function () {
    $empresaA = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa A SAC',
        'estado' => 'ACTIVA',
    ]);
    $empresaB = Empresa::query()->create([
        'ruc' => '20100070971',
        'razon_social' => 'Empresa B SAC',
        'estado' => 'ACTIVA',
    ]);

    $comprobanteDeA = crearComprobantePanel($empresaA->id);
    crearComprobantePanel($empresaB->id);

    actuarComoUsuarioEmpresa($empresaA->id);

    Livewire::test(ListComprobantes::class)
        ->assertCanSeeTableRecords([$comprobanteDeA])
        ->assertCountTableRecords(1);
});

it('nunca permite ver por URL directa el comprobante de otra empresa', function () {
    $empresaA = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa A SAC',
        'estado' => 'ACTIVA',
    ]);
    $empresaB = Empresa::query()->create([
        'ruc' => '20100070971',
        'razon_social' => 'Empresa B SAC',
        'estado' => 'ACTIVA',
    ]);

    $comprobanteDeB = crearComprobantePanel($empresaB->id);

    actuarComoUsuarioEmpresa($empresaA->id);

    $this->get("/app/comprobantes/{$comprobanteDeB->id}")->assertNotFound();
});

it('un super_admin sin empresa no puede entrar al panel de empresa y es redirigido al suyo', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Administrador',
        'email' => 'admin-sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));

    $this->actingAs($usuario)->get('/app')->assertRedirect('/admin');
});

it('crea un cliente desde el panel de empresa sin pedir la empresa (se resuelve del usuario autenticado)', function () {
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Panel Clientes SAC',
        'estado' => 'ACTIVA',
    ]);
    actuarComoUsuarioEmpresa($empresa->id);

    Livewire::test(CreateCliente::class)
        ->fillForm([
            'tipo_documento' => '1',
            'numero_documento' => '45678912',
            'razon_social' => 'Juan Pérez',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('clientes', [
        'empresa_id' => $empresa->id,
        'tipo_documento' => '1',
        'numero_documento' => '45678912',
        'razon_social' => 'Juan Pérez',
    ]);
});

it('un usuario de empresa ve solo los clientes de su propia empresa', function () {
    $empresaA = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa A SAC',
        'estado' => 'ACTIVA',
    ]);
    $empresaB = Empresa::query()->create([
        'ruc' => '20100070971',
        'razon_social' => 'Empresa B SAC',
        'estado' => 'ACTIVA',
    ]);

    $clienteDeA = Cliente::query()->create([
        'empresa_id' => $empresaA->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070972',
        'razon_social' => 'Cliente de A',
    ]);
    Cliente::query()->create([
        'empresa_id' => $empresaB->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070973',
        'razon_social' => 'Cliente de B',
    ]);

    actuarComoUsuarioEmpresa($empresaA->id);

    Livewire::test(ListClientes::class)
        ->assertCanSeeTableRecords([$clienteDeA])
        ->assertCountTableRecords(1);
});

it('nunca permite ver por URL directa el cliente de otra empresa', function () {
    $empresaA = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa A SAC',
        'estado' => 'ACTIVA',
    ]);
    $empresaB = Empresa::query()->create([
        'ruc' => '20100070971',
        'razon_social' => 'Empresa B SAC',
        'estado' => 'ACTIVA',
    ]);

    $clienteDeB = Cliente::query()->create([
        'empresa_id' => $empresaB->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070973',
        'razon_social' => 'Cliente de B',
    ]);

    actuarComoUsuarioEmpresa($empresaA->id);

    $this->get("/app/clientes/{$clienteDeB->id}/edit")->assertNotFound();
});
