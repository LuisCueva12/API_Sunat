<?php

declare(strict_types=1);

namespace App\Services\Comprobantes;

use App\Filament\Support\ComprobanteFormato;
use App\Models\Comprobante;
use Barryvdh\DomPDF\Facade\Pdf;
use DOMDocument;
use InvalidArgumentException;
use Luecano\NumeroALetras\NumeroALetras;
use Modules\Facturacion\Domain\Puertos\AlmacenPrivado;
use RuntimeException;

final class GeneradorRepresentacionImpresa
{
    private const DS_NAMESPACE = 'http://www.w3.org/2000/09/xmldsig#';

    private const VERSION_PLANTILLA = 'v1';

    public function __construct(
        private readonly AlmacenPrivado $almacen,
        private readonly CodigoQrSunat $codigoQr,
    ) {}

    public function generar(Comprobante $comprobante): RepresentacionImpresa
    {
        if (! in_array($comprobante->estado, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'], true)) {
            throw new InvalidArgumentException('La representación impresa solo está disponible para comprobantes aceptados.');
        }

        $comprobante->loadMissing(['items', 'referencia', 'empresa.establecimientos']);
        $rutaBase = $this->rutaBase($comprobante);
        $rutaPdf = "{$rutaBase}/representacion-".self::VERSION_PLANTILLA.'.pdf';
        $nombreArchivo = $this->nombreArchivo($comprobante);

        if ($this->almacen->existe($rutaPdf)) {
            return new RepresentacionImpresa($nombreArchivo, $this->almacen->leer($rutaPdf), $rutaPdf);
        }

        $xml = $this->almacen->leer("{$rutaBase}/comprobante.xml");
        $digestValue = $this->extraerDigestValue($xml);
        $cadenaQr = $this->codigoQr->cadena($comprobante, $digestValue);
        $qrPng = $this->codigoQr->png($cadenaQr);
        $emisor = $this->datosEmisor($comprobante);
        [$simboloMoneda, $monedaTexto, $fraccionMoneda] = match ($comprobante->moneda) {
            'PEN' => ['S/', 'SOLES', 'CÉNTIMOS'],
            'USD' => ['US$', 'DÓLARES AMERICANOS', 'CENTAVOS'],
            default => [$comprobante->moneda, $comprobante->moneda, 'CENTAVOS'],
        };

        $pdf = Pdf::loadView('pdf.comprobante', [
            'comprobante' => $comprobante,
            'emisor' => $emisor,
            'tipoComprobante' => ComprobanteFormato::etiquetaTipo($comprobante->tipo),
            'digestValue' => $digestValue,
            'qrBase64' => base64_encode($qrPng),
            'simboloMoneda' => $simboloMoneda,
            'totalEnLetras' => (new NumeroALetras)->toMoney((float) $comprobante->total, 2, $monedaTexto, $fraccionMoneda),
        ])->setPaper('a4')->setOption('isRemoteEnabled', false);

        $contenido = $pdf->output();

        if (! str_starts_with($contenido, '%PDF-')) {
            throw new RuntimeException('No se pudo construir un archivo PDF válido.');
        }

        $this->almacen->guardar($rutaPdf, $contenido);

        return new RepresentacionImpresa($nombreArchivo, $contenido, $rutaPdf);
    }

    private function extraerDigestValue(string $xml): string
    {
        if (trim($xml) === '') {
            throw new RuntimeException('No se encontró el XML firmado del comprobante.');
        }

        $documento = new DOMDocument;
        $estadoAnterior = libxml_use_internal_errors(true);

        try {
            $cargado = $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($estadoAnterior);
        }

        if (! $cargado) {
            throw new RuntimeException('El XML firmado del comprobante no es válido.');
        }

        $nodosDigest = $documento->getElementsByTagNameNS(self::DS_NAMESPACE, 'DigestValue');

        if ($nodosDigest->length === 0) {
            throw new RuntimeException('El XML firmado no contiene el valor resumen requerido por SUNAT.');
        }

        $digest = trim($nodosDigest->item(0)->textContent);

        if ($digest === '') {
            throw new RuntimeException('El XML firmado no contiene el valor resumen requerido por SUNAT.');
        }

        return $digest;
    }

    /** @return array<string, string|null> */
    private function datosEmisor(Comprobante $comprobante): array
    {
        $snapshot = $comprobante->snapshot_emisor;
        $establecimiento = $comprobante->empresa->establecimientos
            ->sortByDesc('es_principal')
            ->first();

        return [
            'ruc' => (string) data_get($snapshot, 'ruc', $comprobante->empresa->ruc),
            'razon_social' => (string) data_get($snapshot, 'razon_social', $comprobante->empresa->razon_social),
            'nombre_comercial' => data_get($snapshot, 'nombre_comercial', $comprobante->empresa->nombre_comercial),
            'direccion' => data_get($snapshot, 'direccion', $establecimiento?->direccion),
            'ubigeo' => data_get($snapshot, 'ubigeo', $establecimiento?->ubigeo),
            'distrito' => data_get($snapshot, 'distrito', $establecimiento?->distrito),
            'provincia' => data_get($snapshot, 'provincia', $establecimiento?->provincia),
            'departamento' => data_get($snapshot, 'departamento', $establecimiento?->departamento),
        ];
    }

    private function rutaBase(Comprobante $comprobante): string
    {
        return sprintf(
            'empresas/%s/comprobantes/%s/%s/%s',
            $comprobante->empresa_id,
            $comprobante->fecha_emision->format('Y'),
            $comprobante->fecha_emision->format('m'),
            $comprobante->id,
        );
    }

    private function nombreArchivo(Comprobante $comprobante): string
    {
        $ruc = (string) data_get($comprobante->snapshot_emisor, 'ruc', 'comprobante');
        $tipo = match ($comprobante->tipo) {
            'FACTURA' => '01',
            'BOLETA' => '03',
            'NOTA_CREDITO' => '07',
            'NOTA_DEBITO' => '08',
            default => 'documento',
        };

        return sprintf('%s-%s-%s-%d.pdf', $ruc, $tipo, $comprobante->serie, $comprobante->correlativo);
    }
}
