<?php

declare(strict_types=1);

use App\Models\Empresa;
use Illuminate\Support\Facades\Storage;
use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;
use Modules\Facturacion\Domain\Puertos\EnviadorComprobanteElectronico;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use Modules\Facturacion\Domain\ValueObjects\Ruc;

it('persiste el intento SUNAT y los eventos reales del procesamiento', function () {
    Storage::fake('local');
    $empresa = Empresa::query()->create([
        'ruc' => '20100070970',
        'razon_social' => 'Empresa Trazabilidad SAC',
        'estado' => 'ACTIVA',
    ]);
    $comprobante = crearComprobantePanel($empresa->id, ['estado' => 'REGISTRADO']);
    $datosSunat = new DatosSunatEmpresa(
        new DatosEmisor(new Ruc($empresa->ruc), $empresa->razon_social, null, null, null),
        new CertificadoDigital('certificado'),
        'usuario',
        'clave',
        'https://sunat.test',
    );

    $proveedor = Mockery::mock(ProveedorDatosSunat::class);
    $proveedor->shouldReceive('paraEmpresa')->once()->andReturn($datosSunat);
    app()->instance(ProveedorDatosSunat::class, $proveedor);
    $generador = Mockery::mock(GeneradorXmlFirmado::class);
    $generador->shouldReceive('generar')->once()->andReturn('<Invoice/>');
    app()->instance(GeneradorXmlFirmado::class, $generador);
    $enviador = Mockery::mock(EnviadorComprobanteElectronico::class);
    $enviador->shouldReceive('enviar')->once()->andReturn(ResultadoEnvio::conRespuestaSunat(
        codigo: '0',
        descripcion: 'Aceptado',
        notas: [],
        cdrZipBase64: base64_encode('cdr'),
    ));
    $fabrica = Mockery::mock(FabricaEnviadorComprobante::class);
    $fabrica->shouldReceive('crear')->once()->andReturn($enviador);
    app()->instance(FabricaEnviadorComprobante::class, $fabrica);

    app(ProcesarEnvioComprobante::class)->ejecutar($empresa->id, $comprobante->id, 'BETA');

    $this->assertDatabaseHas('envios_sunat', [
        'comprobante_id' => $comprobante->id,
        'intento' => 1,
        'entorno' => 'BETA',
        'codigo_respuesta_sunat' => '0',
    ]);
    $this->assertDatabaseHas('eventos_comprobante', [
        'comprobante_id' => $comprobante->id,
        'tipo_evento' => 'PROCESANDO',
    ]);
    $this->assertDatabaseHas('eventos_comprobante', [
        'comprobante_id' => $comprobante->id,
        'tipo_evento' => 'ACEPTADO',
    ]);
});
