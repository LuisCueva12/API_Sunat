<?php

declare(strict_types=1);

use Modules\Facturacion\Domain\Comprobante\Comprobante;
use Modules\Facturacion\Domain\Comprobante\ItemComprobante;
use Modules\Facturacion\Domain\Comprobante\ReferenciaComprobante;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Comprobante\TotalesComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Puertos\RepositorioComprobante;
use Modules\Facturacion\Domain\Validacion\ValidadorNotaDebito;
use Modules\Facturacion\Domain\ValueObjects\Dinero;
use Modules\Facturacion\Domain\ValueObjects\DocumentoIdentidad;
use Modules\Facturacion\Domain\ValueObjects\Moneda;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Ruc;
use Modules\Facturacion\Domain\ValueObjects\Serie;
use Modules\Facturacion\Domain\ValueObjects\TipoDocumentoIdentidad;

function repositorioComprobanteFalsoParaDebito(?Comprobante $comprobanteExistente): RepositorioComprobante
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

function facturaAceptadaParaDebito(): Comprobante
{
    $factura = Comprobante::registrar(
        id: 'factura-original-nd',
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

function notaDebitoDePrueba(?ReferenciaComprobante $referencia, bool $conItem = true): Comprobante
{
    $comprobante = Comprobante::registrar(
        id: 'nd-test',
        empresaId: 'empresa-test',
        tipo: TipoComprobante::NotaDebito,
        numero: new NumeroComprobante(new Serie('FD01'), 1),
        moneda: Moneda::PEN,
        receptorDocumento: new DocumentoIdentidad(TipoDocumentoIdentidad::Ruc, (string) new Ruc('20100070970')),
        receptorRazonSocial: 'Cliente SAC',
        fechaEmision: new DateTimeImmutable('2026-08-15'),
        referencia: $referencia,
    );

    if ($conItem) {
        $comprobante->agregarItem(new ItemComprobante(
            numeroOrden: 1,
            descripcion: 'Intereses por mora',
            unidadMedida: 'NIU',
            cantidad: 1.0,
            valorUnitario: Dinero::desde('50.00'),
            precioUnitario: Dinero::desde('59.00'),
            tipoAfectacionIgv: '10',
            montoIgv: Dinero::desde('9.00'),
            montoValorVenta: Dinero::desde('50.00'),
            descuento: Dinero::cero(),
        ));
        $comprobante->definirTotales(new TotalesComprobante(
            opGravada: Dinero::desde('50.00'),
            opExonerada: Dinero::cero(),
            opInafecta: Dinero::cero(),
            opGratuita: Dinero::cero(),
            totalIgv: Dinero::desde('9.00'),
            totalDescuentos: Dinero::cero(),
            total: Dinero::desde('59.00'),
        ));
    }

    return $comprobante;
}

it('acepta una nota de débito que referencia una factura existente con motivo', function () {
    $referencia = new ReferenciaComprobante('factura-original-nd', '01', 'Intereses por mora');
    $repositorio = repositorioComprobanteFalsoParaDebito(facturaAceptadaParaDebito());

    (new ValidadorNotaDebito($repositorio))->validar(notaDebitoDePrueba($referencia));
})->throwsNoExceptions();

it('rechaza una nota de débito sin referencia', function () {
    $repositorio = repositorioComprobanteFalsoParaDebito(null);

    (new ValidadorNotaDebito($repositorio))->validar(notaDebitoDePrueba(null));
})->throws(ComprobanteInvalidoException::class);

it('rechaza una nota de débito cuyo comprobante referenciado no existe', function () {
    $referencia = new ReferenciaComprobante('no-existe', '01', 'Motivo cualquiera');
    $repositorio = repositorioComprobanteFalsoParaDebito(null);

    (new ValidadorNotaDebito($repositorio))->validar(notaDebitoDePrueba($referencia));
})->throws(ComprobanteInvalidoException::class);

it('rechaza un código de motivo que no pertenece al catálogo 10 de SUNAT', function () {
    $referencia = new ReferenciaComprobante('factura-original-nd', '99', 'Motivo inventado');
    $repositorio = repositorioComprobanteFalsoParaDebito(facturaAceptadaParaDebito());

    (new ValidadorNotaDebito($repositorio))->validar(notaDebitoDePrueba($referencia));
})->throws(ComprobanteInvalidoException::class, "El código de motivo '99' no pertenece al catálogo 10 de SUNAT");

it('rechaza un código de motivo del catálogo de nota de crédito reutilizado en una nota de débito', function () {
    // '06' (Devolución total) es válido en el catálogo 09 pero no existe en el catálogo 10.
    $referencia = new ReferenciaComprobante('factura-original-nd', '06', 'Devolución total');
    $repositorio = repositorioComprobanteFalsoParaDebito(facturaAceptadaParaDebito());

    (new ValidadorNotaDebito($repositorio))->validar(notaDebitoDePrueba($referencia));
})->throws(ComprobanteInvalidoException::class);
