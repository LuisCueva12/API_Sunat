<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\ReferenciaComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaCredito;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function repositorioComprobanteFalso(?Comprobante $comprobanteExistente): RepositorioComprobante
{
    return new class($comprobanteExistente) implements RepositorioComprobante
    {
        public function __construct(private readonly ?Comprobante $existente) {}

        public function guardar(Comprobante $comprobante): void {}

        public function actualizarEstado(Comprobante $comprobante, ?string $xmlSha256 = null, ?string $cdrSha256 = null): void {}

        public function buscarPorId(string $empresaId, string $id): ?Comprobante
        {
            return $this->existente;
        }
    };
}

function facturaAceptadaDePrueba(): Comprobante
{
    $factura = Comprobante::registrar(
        id: 'factura-original',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::Factura,
        numero: new NumeroComprobante(new Serie('F001'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, (string) new Ruc('20100070970')),
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
    );
    $factura->marcarProcesando();
    $factura->marcarAceptado();

    return $factura;
}

function notaCreditoDePrueba(?ReferenciaComprobante $referencia, bool $conItem = true): Comprobante
{
    $comprobante = Comprobante::registrar(
        id: 'nc-test',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::NotaCredito,
        numero: new NumeroComprobante(new Serie('FC01'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, (string) new Ruc('20100070970')),
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
        referencia: $referencia,
    );

    if ($conItem) {
        $comprobante->agregarItem(new ItemComprobante(
            numeroOrden: 1,
            descripcion: 'Devolución parcial',
            unidadMedida: 'NIU',
            cantidad: 1.0,
            valorUnitario: Dinero::desde('100.00'),
            precioUnitario: Dinero::desde('118.00'),
            tipoAfectacionIgv: '10',
            montoIgv: Dinero::desde('18.00'),
            montoValorVenta: Dinero::desde('100.00'),
            descuento: Dinero::cero(),
        ));
        $comprobante->definirTotales(new TotalesComprobante(
            opGravada: Dinero::desde('100.00'),
            opExonerada: Dinero::cero(),
            opInafecta: Dinero::cero(),
            opGratuita: Dinero::cero(),
            totalIgv: Dinero::desde('18.00'),
            totalDescuentos: Dinero::cero(),
            total: Dinero::desde('118.00'),
        ));
    }

    return $comprobante;
}

it('acepta una nota de crédito que referencia una factura existente con motivo', function () {
    $referencia = new ReferenciaComprobante('factura-original', '01', 'Anulación de la operación');
    $repositorio = repositorioComprobanteFalso(facturaAceptadaDePrueba());

    (new ValidadorNotaCredito($repositorio))->validar(notaCreditoDePrueba($referencia));
})->throwsNoExceptions();

it('rechaza una nota de crédito sin referencia', function () {
    $repositorio = repositorioComprobanteFalso(null);

    (new ValidadorNotaCredito($repositorio))->validar(notaCreditoDePrueba(null));
})->throws(ComprobanteInvalidoException::class);

it('rechaza una nota de crédito cuyo comprobante referenciado no existe', function () {
    $referencia = new ReferenciaComprobante('no-existe', '01', 'Motivo cualquiera');
    $repositorio = repositorioComprobanteFalso(null);

    (new ValidadorNotaCredito($repositorio))->validar(notaCreditoDePrueba($referencia));
})->throws(ComprobanteInvalidoException::class);

it('rechaza una nota de crédito que referencia otra nota de crédito', function () {
    $notaOriginal = notaCreditoDePrueba(new ReferenciaComprobante('factura-original', '01', 'Motivo'));
    $referencia = new ReferenciaComprobante('nc-original', '01', 'Motivo');
    $repositorio = repositorioComprobanteFalso($notaOriginal);

    (new ValidadorNotaCredito($repositorio))->validar(notaCreditoDePrueba($referencia));
})->throws(ComprobanteInvalidoException::class);
