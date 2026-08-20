<?php

declare(strict_types=1);

use App\Filament\Empresa\Resources\Clientes\Pages\CreateCliente;
use App\Filament\Empresa\Resources\Clientes\Pages\ListClientes;
use App\Filament\Empresa\Resources\Comprobantes\Pages\CreateComprobante;
use App\Filament\Empresa\Resources\Comprobantes\Pages\ListComprobantes;
use App\Jobs\ProcesarComprobante;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Empresa;
use App\Models\Serie;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

function crearSerieParaPanelEmpresa(string $empresaId, string $tipo, string $serie): Serie
{
    return Serie::query()->create([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $empresaId,
        'tipo_comprobante' => $tipo,
        'serie' => $serie,
        'correlativo_actual' => 0,
        'activa' => true,
    ]);
}

it('redirige a los invitados al login del panel de empresa', function () {
    $this->get('/gestion')->assertRedirect('/gestion/login');
});

it('rechaza un usuario sin empresa asignada y cierra su sesión', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Sin empresa',
        'email' => 'sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);

    $this->actingAs($usuario)->get('/gestion')->assertRedirect('/admin/login');
    $this->assertGuest('web');
});

it('permite a un usuario de empresa entrar a su panel', function () {
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Panel SAC',
        'estado' => 'ACTIVA',
    ]);
    actuarComoUsuarioEmpresa($empresa->id);

    $this->get('/gestion/comprobantes')->assertOk();
    $this->get('/gestion/comprobantes/create')->assertOk();
    $this->get('/gestion/clientes')->assertOk();
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
        ->assertActionExists('create')
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

    $this->get("/gestion/comprobantes/{$comprobanteDeB->id}")->assertNotFound();
});

it('un super_admin sin empresa no puede entrar al panel de empresa y es redirigido al suyo', function () {
    $usuario = Usuario::query()->create([
        'name' => 'Administrador',
        'email' => 'admin-sin-empresa@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    $usuario->assignRole(Role::findOrCreate('super_admin', 'web'));

    $this->actingAs($usuario)->get('/gestion/comprobantes')->assertRedirect('/admin');
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

    $this->get("/gestion/clientes/{$clienteDeB->id}/edit")->assertNotFound();
});

it('emite una boleta en un solo flujo sin registrar previamente al cliente', function () {
    Queue::fake();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Emisora SAC',
        'estado' => 'ACTIVA',
    ]);
    crearSerieParaPanelEmpresa($empresa->id, 'BOLETA', 'B001');
    actuarComoUsuarioEmpresa($empresa->id);

    Livewire::test(CreateComprobante::class)
        ->fillForm([
            'tipo' => 'BOLETA',
            'serie' => 'B001',
            'receptor_tipo_documento' => 'SIN_DOCUMENTO',
            'receptor_numero_documento' => '',
            'receptor_razon_social' => 'María Pérez',
            'items' => [[
                'descripcion' => 'Servicio de mantenimiento',
                'unidad_medida' => 'ZZ',
                'cantidad' => 2,
                'valor_unitario' => '10.00',
                'descuento' => null,
                'tipo_afectacion_igv' => '10',
            ]],
            'moneda' => 'PEN',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $comprobante = Comprobante::query()->sole();
    expect($comprobante->tipo)->toBe('BOLETA')
        ->and($comprobante->receptor_tipo_documento)->toBe('0')
        ->and($comprobante->receptor_numero_documento)->toBe('')
        ->and($comprobante->receptor_razon_social)->toBe('María Pérez')
        ->and($comprobante->total)->toBe('23.60')
        ->and(Cliente::query()->count())->toBe(0);

    Queue::assertPushed(ProcesarComprobante::class);
});

it('emite una factura con RUC escrito directamente sin registrar previamente al cliente', function () {
    Queue::fake();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Emisora SAC',
        'estado' => 'ACTIVA',
    ]);
    crearSerieParaPanelEmpresa($empresa->id, 'FACTURA', 'F001');
    actuarComoUsuarioEmpresa($empresa->id);

    Livewire::test(CreateComprobante::class)
        ->fillForm([
            'tipo' => 'FACTURA',
            'serie' => 'F001',
            'receptor_tipo_documento' => 'RUC',
            'receptor_numero_documento' => '20100066603',
            'receptor_razon_social' => 'Comprador Directo SAC',
            'items' => [[
                'descripcion' => 'Producto de venta directa',
                'unidad_medida' => 'NIU',
                'cantidad' => 1,
                'valor_unitario' => '100.00',
                'descuento' => null,
                'tipo_afectacion_igv' => '10',
            ]],
            'moneda' => 'PEN',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $comprobante = Comprobante::query()->sole();
    expect($comprobante->tipo)->toBe('FACTURA')
        ->and($comprobante->receptor_tipo_documento)->toBe('6')
        ->and($comprobante->receptor_numero_documento)->toBe('20100066603')
        ->and($comprobante->receptor_razon_social)->toBe('Comprador Directo SAC')
        ->and($comprobante->total)->toBe('118.00')
        ->and(Cliente::query()->count())->toBe(0);

    Queue::assertPushed(ProcesarComprobante::class);
});

it('autocompleta el receptor al elegir un cliente guardado en la emisión', function () {
    Queue::fake();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Emisora SAC',
        'estado' => 'ACTIVA',
    ]);
    crearSerieParaPanelEmpresa($empresa->id, 'FACTURA', 'F001');
    $cliente = Cliente::query()->create([
        'empresa_id' => $empresa->id,
        'tipo_documento' => '6',
        'numero_documento' => '20100066603',
        'razon_social' => 'Cliente Frecuente SAC',
    ]);
    actuarComoUsuarioEmpresa($empresa->id);

    Livewire::test(CreateComprobante::class)
        ->set('data.tipo', 'FACTURA')
        ->set('data.cliente_id', $cliente->id)
        ->assertSchemaStateSet([
            'serie' => 'F001',
            'receptor_tipo_documento' => 'RUC',
            'receptor_numero_documento' => '20100066603',
            'receptor_razon_social' => 'Cliente Frecuente SAC',
        ])
        ->set('data.items', [[
            'descripcion' => 'Servicio recurrente',
            'unidad_medida' => 'ZZ',
            'cantidad' => 1,
            'valor_unitario' => '50.00',
            'descuento' => null,
            'tipo_afectacion_igv' => '10',
        ]])
        ->call('create')
        ->assertHasNoFormErrors();

    $comprobante = Comprobante::query()->sole();
    expect($comprobante->receptor_numero_documento)->toBe('20100066603')
        ->and($comprobante->receptor_razon_social)->toBe('Cliente Frecuente SAC');

    Queue::assertPushed(ProcesarComprobante::class);
});

it('impide usar DNI en una factura desde el formulario', function () {
    Queue::fake();

    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Emisora SAC',
        'estado' => 'ACTIVA',
    ]);
    crearSerieParaPanelEmpresa($empresa->id, 'FACTURA', 'F001');
    actuarComoUsuarioEmpresa($empresa->id);

    Livewire::test(CreateComprobante::class)
        ->fillForm([
            'tipo' => 'FACTURA',
            'serie' => 'F001',
            'receptor_tipo_documento' => 'DNI',
            'receptor_numero_documento' => '12345678',
            'receptor_razon_social' => 'Cliente con DNI',
            'items' => [[
                'descripcion' => 'Producto',
                'unidad_medida' => 'NIU',
                'cantidad' => 1,
                'valor_unitario' => '10.00',
                'descuento' => null,
                'tipo_afectacion_igv' => '10',
            ]],
            'moneda' => 'PEN',
        ])
        ->call('create')
        ->assertHasFormErrors(['receptor_tipo_documento']);

    expect(Comprobante::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});
