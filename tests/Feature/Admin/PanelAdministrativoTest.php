<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Admin\Resources\Series\Pages\CreateSerie;
use App\Models\Empresa;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('redirige a los invitados al login del panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('rechaza usuarios sin el rol de administrador', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Operador sin rol',
        'email' => 'sin-rol@example.test',
        'password' => 'Clave-segura-123!',
    ]);

    $this->actingAs($usuario)->get('/admin')->assertForbidden();
});

it('rechaza usuarios asociados a un tenant aunque tengan el rol administrativo', function () {
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Tenant SAC',
        'estado' => 'ACTIVA',
    ]);
    $usuario = Usuario::query()->create([
        'empresa_id' => $empresa->id,
        'name' => 'Usuario tenant',
        'email' => 'tenant@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));

    $this->actingAs($usuario)->get('/admin')->assertForbidden();
});

it('permite al super administrador interno usar los recursos iniciales', function () {
    $usuario = Usuario::query()->create([
        'empresa_id' => null,
        'name' => 'Administrador',
        'email' => 'admin@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs($usuario);

    $this->get('/admin')->assertOk();
    $this->get('/admin/empresas')->assertOk();
    $this->get('/admin/establecimientos')->assertOk();
    $this->get('/admin/series')->assertOk();
});

it('crea el primer administrador con contraseña oculta y rol explícito', function () {
    $this->artisan('facturacion:crear-admin', [
        'email' => 'primer-admin@example.test',
        '--name' => 'Primer Administrador',
    ])
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'Clave-segura-123!')
        ->expectsQuestion('Confirma la contraseña', 'Clave-segura-123!')
        ->assertSuccessful();

    $usuario = Usuario::query()->where('email', 'primer-admin@example.test')->firstOrFail();

    expect($usuario->empresa_id)->toBeNull()
        ->and($usuario->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('Clave-segura-123!', $usuario->password))->toBeTrue();
});

it('crea empresas y series desde el panel usando los casos de uso', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Administrador',
        'email' => 'admin-recursos@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($usuario);

    Livewire::test(CreateEmpresa::class)
        ->fillForm([
            'ruc' => '20100070970',
            'razon_social' => 'Empresa creada desde panel SAC',
            'nombre_comercial' => 'Panel SAC',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $empresa = Empresa::query()->where('ruc', '20100070970')->firstOrFail();

    Livewire::test(CreateSerie::class)
        ->fillForm([
            'empresa_id' => $empresa->id,
            'tipo_comprobante' => 'FACTURA',
            'serie' => 'f001',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('series', [
        'empresa_id' => $empresa->id,
        'tipo_comprobante' => 'FACTURA',
        'serie' => 'F001',
        'correlativo_actual' => 0,
    ]);
});
