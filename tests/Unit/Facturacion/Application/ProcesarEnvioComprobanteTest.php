<?php

declare(strict_types=1);

use Modules\Facturacion\Application\CasosDeUso\ProcesarEnvioComprobante;
use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\EstadoComprobante;
use Modules\Facturacion\Domain\Comprobante\ResultadoEnvio;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\DatosEmisor;
use Modules\Facturacion\Domain\Empresa\DatosSunatEmpresa;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use Modules\Facturacion\Domain\Puertos\DespachadorWebhooks;
use Modules\Facturacion\Domain\Puertos\EnviadorComprobanteElectronico;
use Modules\Facturacion\Domain\Puertos\FabricaEnviadorComprobante;
use Modules\Facturacion\Domain\Puertos\GeneradorXmlFirmado;
use Modules\Facturacion\Domain\Puertos\ProveedorDatosSunat;
use Modules\Facturacion\Domain\Puertos\RegistradorTrazabilidadComprobante;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\ValueObjects\CertificadoDigital;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function comprobanteParaProcesamientoConError(): Comprobante
{
    return Comprobante::registrar(
        id: 'comprobante-procesamiento-1',
        empresaId: 'empresa-1',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, '20100070970'),
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-20'),
    );
}

it('marca error y relanza el fallo técnico para activar los reintentos automáticos del Job', function () {
    $comprobante = comprobanteParaProcesamientoConError();
    $repositorio = Mockery::mock(RepositorioComprobante::class);
    $repositorio->shouldReceive('buscarPorId')->once()->andReturn($comprobante);
    $repositorio->shouldReceive('actualizarEstado')->twice();

    $datosSunat = new DatosSunatEmpresa(
        new DatosEmisor(new Ruc('20100070970'), 'Empresa SAC', null, null, null),
        new CertificadoDigital('certificado'),
        'usuario',
        'clave',
        'https://sunat.test',
    );

    $proveedor = Mockery::mock(ProveedorDatosSunat::class);
    $proveedor->shouldReceive('paraEmpresa')->once()->andReturn($datosSunat);
    $generador = Mockery::mock(GeneradorXmlFirmado::class);
    $generador->shouldReceive('generar')->once()->andReturn('<xml/>');
    $almacen = Mockery::mock(AlmacenPrivado::class);
    $almacen->shouldReceive('guardar')->once();
    $enviador = Mockery::mock(EnviadorComprobanteElectronico::class);
    $enviador->shouldReceive('enviar')->once()->andReturn(ResultadoEnvio::errorTecnico('timeout SUNAT'));
    $fabrica = Mockery::mock(FabricaEnviadorComprobante::class);
    $fabrica->shouldReceive('crear')->once()->andReturn($enviador);
    $trazabilidad = Mockery::mock(RegistradorTrazabilidadComprobante::class);
    $trazabilidad->shouldReceive('registrarEvento')->twice();
    $trazabilidad->shouldReceive('registrarEnvio')->once();
    $webhooks = Mockery::mock(DespachadorWebhooks::class);
    $webhooks->shouldReceive('despacharEventoTerminal')->once();

    $casoDeUso = new ProcesarEnvioComprobante(
        $repositorio,
        $proveedor,
        $generador,
        $fabrica,
        $almacen,
        $trazabilidad,
        $webhooks,
    );

    expect(fn () => $casoDeUso->ejecutar('empresa-1', $comprobante->id(), 'BETA'))
        ->toThrow(RuntimeException::class, 'timeout SUNAT')
        ->and($comprobante->estado())->toBe(EstadoComprobante::Error)
        ->and($comprobante->intentosEnvio())->toBe(1);
});
