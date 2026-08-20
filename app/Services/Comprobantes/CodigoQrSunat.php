<?php

declare(strict_types=1);

namespace App\Services\Comprobantes;

use App\Models\Comprobante;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use InvalidArgumentException;

final class CodigoQrSunat
{
    public function cadena(Comprobante $comprobante, string $digestValue): string
    {
        $digestValue = trim($digestValue);

        if ($digestValue === '') {
            throw new InvalidArgumentException('El XML no contiene el valor resumen requerido para el QR.');
        }

        return implode('|', [
            (string) data_get($comprobante->snapshot_emisor, 'ruc', ''),
            $this->tipoDocumentoSunat($comprobante->tipo),
            $comprobante->serie,
            (string) $comprobante->correlativo,
            number_format((float) $comprobante->total_igv, 2, '.', ''),
            number_format((float) $comprobante->total, 2, '.', ''),
            $comprobante->fecha_emision->format('Y-m-d'),
            $comprobante->receptor_tipo_documento,
            $comprobante->receptor_numero_documento,
            $digestValue,
        ]);
    }

    public function png(string $cadena): string
    {
        $opciones = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => false,
            'eccLevel' => EccLevel::Q,
            'scale' => 6,
            'addQuietzone' => true,
            'quietzoneSize' => 4,
        ]);

        return (new QRCode($opciones))->render($cadena);
    }

    private function tipoDocumentoSunat(string $tipo): string
    {
        return match ($tipo) {
            'FACTURA' => '01',
            'BOLETA' => '03',
            'NOTA_CREDITO' => '07',
            'NOTA_DEBITO' => '08',
            default => throw new InvalidArgumentException("El tipo de comprobante {$tipo} no admite representación impresa."),
        };
    }
}
