<?php

declare(strict_types=1);

use App\Models\CertificadoDigital as CertificadoEloquent;
use App\Models\Empresa as EmpresaEloquent;
use Modules\Facturacion\Application\CasosDeUso\CrearCertificadoDigital;
use Modules\Facturacion\Application\DTO\CrearCertificadoDigitalInput;
use Modules\Facturacion\Domain\Excepciones\EmpresaInvalidaException;

beforeEach(function () {
    $this->empresa = EmpresaEloquent::create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa de Prueba SAC',
        'estado' => 'ACTIVA',
    ]);
});

it('crea y cifra un certificado digital activo', function () {
    $certificado = app(CrearCertificadoDigital::class)->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: $this->empresa->id,
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'clave-secreta',
        alias: 'Principal',
    ));

    $this->assertDatabaseHas('certificados_digitales', [
        'id' => $certificado->id(),
        'empresa_id' => $this->empresa->id,
        'estado' => 'ACTIVO',
    ]);

    $fila = CertificadoEloquent::query()->find($certificado->id());

    expect($fila->password_cifrado)->toBe('clave-secreta')
        ->and($fila->contenido_cifrado)->toBe($certificado->contenidoPem());

    $crudo = DB::table('certificados_digitales')->where('id', $certificado->id())->first();
    expect($crudo->password_cifrado)->not->toBe('clave-secreta');
});

it('reemplaza el certificado activo previo y mantiene el histórico', function () {
    app(CrearCertificadoDigital::class)->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: $this->empresa->id,
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'clave-1',
    ));

    app(CrearCertificadoDigital::class)->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: $this->empresa->id,
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'clave-2',
    ));

    expect(CertificadoEloquent::query()->where('empresa_id', $this->empresa->id)->where('estado', 'ACTIVO')->count())->toBe(1)
        ->and(CertificadoEloquent::query()->where('empresa_id', $this->empresa->id)->where('estado', 'REEMPLAZADO')->count())->toBe(1);
});

it('rechaza crear un certificado para una empresa inexistente', function () {
    app(CrearCertificadoDigital::class)->ejecutar(new CrearCertificadoDigitalInput(
        empresaId: (string) Str::uuid7(),
        contenidoPem: generarCertificadoPemDePrueba(),
        password: 'clave',
    ));
})->throws(EmpresaInvalidaException::class);
