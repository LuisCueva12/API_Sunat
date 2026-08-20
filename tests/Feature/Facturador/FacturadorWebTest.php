<?php

declare(strict_types=1);

use App\Jobs\ProcesarComprobante;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\ProductoServicio;
use App\Models\Serie;
use App\Models\Usuario;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

function crearEmpresaFacturador(string $ruc = '20100070970'): Empresa
{
    return Empresa::query()->create([
        'ruc' => $ruc,
        'razon_social' => 'Empresa Facturadora SAC',
        'estado' => 'ACTIVA',
    ]);
}

function actuarEnFacturador(Empresa $empresa, string $correo = 'ventas@example.test'): Usuario
{
    $usuario = Usuario::query()->create([
        'empresa_id' => $empresa->id,
        'name' => 'Caja principal',
        'email' => $correo,
        'password' => 'Clave-segura-123!',
    ]);

    test()->actingAs($usuario);

    return $usuario;
}

function crearSerieFacturador(Empresa $empresa, string $tipo = 'BOLETA', string $serie = 'B001'): Serie
{
    return Serie::query()->create([
        'id' => (string) Str::uuid7(),
        'empresa_id' => $empresa->id,
        'tipo_comprobante' => $tipo,
        'serie' => $serie,
        'correlativo_actual' => 0,
        'activa' => true,
    ]);
}

it('presenta un acceso propio y protege el facturador para invitados', function () {
    $this->get('/app')->assertRedirect('/app/login');
    $this->get('/app/login')
        ->assertOk()
        ->assertSee('Ingresa para comenzar a vender')
        ->assertDontSee('OAuth')
        ->assertDontSee('Certificados');
});

it('autentica únicamente cuentas vinculadas a una empresa', function () {
    $empresa = crearEmpresaFacturador();
    actuarEnFacturador($empresa);
    auth()->logout();

    $this->post('/app/login', [
        'email' => 'ventas@example.test',
        'password' => 'Clave-segura-123!',
    ])->assertRedirect('/app');

    $this->assertAuthenticated();

    $administrador = Usuario::query()->create([
        'name' => 'Administrador interno',
        'email' => 'interno@example.test',
        'password' => 'Clave-segura-123!',
    ]);
    auth()->logout();

    $this->post('/app/login', [
        'email' => $administrador->email,
        'password' => 'Clave-segura-123!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('muestra solo la navegación operativa del facturador', function () {
    $empresa = crearEmpresaFacturador();
    actuarEnFacturador($empresa);

    $this->get('/app')
        ->assertOk()
        ->assertSeeInOrder([
            'Inicio',
            'Nueva venta',
            'Comprobantes',
            'Clientes',
            'Productos / Servicios',
            'Mi cuenta',
        ])
        ->assertDontSee('Configuración SUNAT')
        ->assertDontSee('OAuth')
        ->assertDontSee('Auditoría');

    $this->get('/app/nueva-venta')->assertOk()->assertSee('Emitir comprobante');
    $this->get('/app/comprobantes')->assertOk();
    $this->get('/app/clientes')->assertOk();
    $this->get('/app/productos')->assertOk();
    $this->get('/app/mi-cuenta')->assertOk();
});

it('emite una boleta sin documento ni alta previa de cliente', function () {
    Queue::fake();
    $empresa = crearEmpresaFacturador();
    actuarEnFacturador($empresa);
    crearSerieFacturador($empresa);

    $respuesta = $this->post('/app/nueva-venta', [
        'tipo' => 'BOLETA',
        'serie' => 'B001',
        'receptor_tipo_documento' => 'SIN_DOCUMENTO',
        'receptor_numero_documento' => '',
        'receptor_razon_social' => 'Cliente varios',
        'items' => [[
            'descripcion' => 'Servicio rápido',
            'unidad_medida' => 'ZZ',
            'cantidad' => 1,
            'valor_unitario' => '100.00',
            'descuento' => null,
            'codigo_producto' => null,
        ]],
    ]);

    $comprobante = $empresa->comprobantes()->sole();
    $respuesta->assertRedirect(route('facturador.ventas.confirmacion', $comprobante->id));
    expect($comprobante->tipo)->toBe('BOLETA')
        ->and($comprobante->receptor_tipo_documento)->toBe('0')
        ->and($comprobante->receptor_numero_documento)->toBe('')
        ->and($comprobante->total)->toBe('118.00')
        ->and(Cliente::query()->count())->toBe(0);

    Queue::assertPushed(ProcesarComprobante::class);
});

it('impide usar DNI en una factura y explica el error sin detalles técnicos', function () {
    $empresa = crearEmpresaFacturador();
    actuarEnFacturador($empresa);
    crearSerieFacturador($empresa, 'FACTURA', 'F001');

    $this->from('/app/nueva-venta')->post('/app/nueva-venta', [
        'tipo' => 'FACTURA',
        'serie' => 'F001',
        'receptor_tipo_documento' => 'DNI',
        'receptor_numero_documento' => '45678912',
        'receptor_razon_social' => 'María Pérez',
        'items' => [[
            'descripcion' => 'Producto',
            'unidad_medida' => 'NIU',
            'cantidad' => 1,
            'valor_unitario' => '10.00',
        ]],
    ])->assertRedirect('/app/nueva-venta')
        ->assertSessionHasErrors('receptor_tipo_documento');
});

it('guarda productos y limita las búsquedas al negocio autenticado', function () {
    $empresaA = crearEmpresaFacturador();
    $empresaB = crearEmpresaFacturador('20100070971');
    actuarEnFacturador($empresaA);

    ProductoServicio::query()->create([
        'empresa_id' => $empresaB->id,
        'codigo' => 'AJENO',
        'nombre' => 'Producto de otra empresa',
        'tipo' => 'PRODUCTO',
        'unidad_medida' => 'NIU',
        'valor_unitario' => '80.00',
        'activo' => true,
    ]);

    $this->post('/app/productos', [
        'codigo' => 'SERV-01',
        'nombre' => 'Asesoría express',
        'tipo' => 'SERVICIO',
        'unidad_medida' => 'ZZ',
        'valor_unitario' => '50.00',
    ])->assertSessionHas('success');

    $this->getJson('/app/buscar/productos?q=empresa')->assertOk()->assertJsonCount(0);
    $this->getJson('/app/buscar/productos?q=Asesoría')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.codigo', 'SERV-01');

    $this->assertDatabaseHas('productos_servicios', [
        'empresa_id' => $empresaA->id,
        'nombre' => 'Asesoría express',
    ]);
});

it('protege comprobantes de otras empresas y nunca expone el error técnico de SUNAT', function () {
    $empresaA = crearEmpresaFacturador();
    $empresaB = crearEmpresaFacturador('20100070971');
    actuarEnFacturador($empresaA);

    $propio = crearComprobantePanel($empresaA->id, [
        'estado' => 'ERROR',
        'ultimo_error' => 'SOAP Fault: XML inválido en nodo cac:TaxTotal',
    ]);
    $ajeno = crearComprobantePanel($empresaB->id, ['correlativo' => 2]);

    $this->get("/app/comprobantes/{$propio->id}")
        ->assertOk()
        ->assertSee('No se pudo completar el envío')
        ->assertDontSee('SOAP Fault')
        ->assertDontSee('cac:TaxTotal');

    $this->get("/app/comprobantes/{$ajeno->id}")->assertNotFound();
    $this->get("/app/ventas/{$ajeno->id}/confirmacion")->assertNotFound();
    $this->getJson("/app/ventas/{$ajeno->id}/estado")->assertNotFound();
});
