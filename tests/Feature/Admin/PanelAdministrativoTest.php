<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Admin\Resources\Comprobantes\Pages\ListComprobantes;
use App\Filament\Admin\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Admin\Resources\Series\Pages\CreateSerie;
use App\Jobs\ProcesarComprobante;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\Serie;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actuarComoAdministradorPanel(string $email = 'admin-panel@example.test'): Usuario
{
    $usuario = Usuario::query()->create([
        'name' => 'Administrador',
        'email' => $email,
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs($usuario);

    return $usuario;
}

function crearComprobantePanel(string $empresaId, array $overrides = []): Comprobante
{
    return Comprobante::query()->create(array_merge([
        'empresa_id' => $empresaId,
        'tipo' => 'FACTURA',
        'serie' => 'F001',
        'correlativo' => 1,
        'estado' => 'ACEPTADO',
        'fecha_emision' => now()->toDateString(),
        'receptor_tipo_documento' => '6',
        'receptor_numero_documento' => '20100070970',
        'receptor_razon_social' => 'Cliente de prueba SAC',
        'op_gravada' => '100.00',
        'total_igv' => '18.00',
        'total' => '118.00',
        'snapshot_emisor' => ['ruc' => '20100070970'],
    ], $overrides));
}

it('redirige a los invitados al login del panel', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('rechaza usuarios sin el rol de administrador y cierra su sesión', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Operador sin rol',
        'email' => 'sin-rol@example.test',
        'password' => 'Clave-segura-123!',
    ]);

    $this->actingAs($usuario)->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest('web');
});

it('rechaza usuarios asociados a un tenant aunque tengan el rol administrativo y los redirige a su panel', function () {
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

    $this->actingAs($usuario)->get('/admin')->assertRedirect('/app');
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
    $this->get('/admin/comprobantes')->assertOk();
    $this->get('/admin/clientes')->assertOk();
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

it('muestra una notificación en vez de un 500 cuando el RUC tiene un dígito verificador inválido', function () {
    actuarComoAdministradorPanel();

    Livewire::test(CreateEmpresa::class)
        ->fillForm([
            'ruc' => '20123456789',
            'razon_social' => 'Empresa RUC Inválido SAC',
        ])
        ->call('create');

    $this->assertDatabaseMissing('empresas', ['ruc' => '20123456789']);
});

it('muestra una notificación en vez de un 500 al duplicar una serie de la misma empresa', function () {
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Series Duplicadas SAC',
        'estado' => 'ACTIVA',
    ]);

    Livewire::test(CreateSerie::class)
        ->fillForm(['empresa_id' => $empresa->id, 'tipo_comprobante' => 'FACTURA', 'serie' => 'F001'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateSerie::class)
        ->fillForm(['empresa_id' => $empresa->id, 'tipo_comprobante' => 'FACTURA', 'serie' => 'F001'])
        ->call('create');

    expect(Serie::query()->where('empresa_id', $empresa->id)->count())->toBe(1);
});

it('lista y muestra el detalle de un comprobante sin errores', function () {
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Comprobantes SAC',
        'estado' => 'ACTIVA',
    ]);
    $comprobante = crearComprobantePanel($empresa->id);

    $this->get('/admin/comprobantes')->assertOk()->assertSee('F001');
    $this->get("/admin/comprobantes/{$comprobante->id}")->assertOk()->assertSee('Cliente de prueba SAC');
});

it('reintenta un comprobante en error desde el panel y encola el envío', function () {
    Queue::fake();
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Reintento SAC',
        'estado' => 'ACTIVA',
    ]);
    $comprobante = crearComprobantePanel($empresa->id, [
        'estado' => 'ERROR',
        'ultimo_error' => 'SUNAT no disponible',
    ]);

    Livewire::test(ListComprobantes::class)
        ->callTableAction('reintentar', $comprobante);

    Queue::assertPushed(
        ProcesarComprobante::class,
        fn (ProcesarComprobante $job): bool => $job->comprobanteId === $comprobante->id,
    );
});

it('no permite reintentar un comprobante que no está en error desde el panel', function () {
    Queue::fake();
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Sin Error SAC',
        'estado' => 'ACTIVA',
    ]);
    $comprobante = crearComprobantePanel($empresa->id);

    Livewire::test(ListComprobantes::class)
        ->assertTableActionHidden('reintentar', $comprobante);

    Queue::assertNothingPushed();
});

it('crea un cliente desde el panel usando el caso de uso', function () {
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Clientes SAC',
        'estado' => 'ACTIVA',
    ]);

    Livewire::test(CreateCliente::class)
        ->fillForm([
            'empresa_id' => $empresa->id,
            'tipo_documento' => '6',
            'numero_documento' => '20100070971',
            'razon_social' => 'Cliente de Prueba SAC',
            'email' => 'contacto@clienteprueba.pe',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('clientes', [
        'empresa_id' => $empresa->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100070971',
        'razon_social' => 'Cliente de Prueba SAC',
    ]);
});

it('muestra una notificación en vez de un 500 al duplicar el documento de un cliente', function () {
    actuarComoAdministradorPanel();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Clientes Duplicados SAC',
        'estado' => 'ACTIVA',
    ]);

    Livewire::test(CreateCliente::class)
        ->fillForm(['empresa_id' => $empresa->id, 'tipo_documento' => '6', 'numero_documento' => '20100070971', 'razon_social' => 'Cliente A'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(CreateCliente::class)
        ->fillForm(['empresa_id' => $empresa->id, 'tipo_documento' => '6', 'numero_documento' => '20100070971', 'razon_social' => 'Cliente B'])
        ->call('create');

    expect(Cliente::query()->where('empresa_id', $empresa->id)->count())->toBe(1);
});
