<?php

declare(strict_types=1);

use App\Filament\Empresa\Resources\Comprobantes\Pages\ListComprobantes;
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

it('rechaza un usuario sin empresa asignada', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Sin empresa',
        'email' => 'sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);

    $this->actingAs($usuario)->get('/app')->assertForbidden();
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

it('un super_admin sin empresa no puede entrar al panel de empresa', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Administrador',
        'email' => 'admin-sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));

    $this->actingAs($usuario)->get('/app')->assertForbidden();
});
