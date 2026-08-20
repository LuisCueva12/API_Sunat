<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10px 12px; }
        * { box-sizing: border-box; }
        body { color: #111; font-family: Helvetica, Arial, sans-serif; font-size: 8px; line-height: 1.3; margin: 0; }
        .center { text-align: center; }
        .right { text-align: right; }
        .brand { font-size: 13px; font-weight: bold; }
        .issuer-name { font-weight: bold; margin-top: 2px; }
        .document-type { font-size: 11px; font-weight: bold; margin-top: 9px; text-transform: uppercase; }
        .document-number { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .separator { border-top: 1px dashed #777; margin: 8px 0; }
        .section-title { font-size: 8px; font-weight: bold; margin-bottom: 4px; text-transform: uppercase; }
        table { border-collapse: collapse; width: 100%; }
        td { padding: 1px 0; vertical-align: top; }
        .label { color: #555; padding-right: 5px; width: 31%; }
        .item { page-break-inside: avoid; }
        .item-description { font-size: 8.5px; font-weight: bold; padding-top: 4px; }
        .item-detail { color: #444; padding-bottom: 4px; }
        .totals { margin-left: 24%; width: 76%; }
        .totals .grand td { border-top: 1px solid #111; font-size: 11px; font-weight: bold; padding-top: 4px; }
        .words { font-size: 7px; margin-top: 7px; }
        .qr { height: 38mm; width: 38mm; }
        .legal { font-size: 6.5px; margin: 4px auto 0; max-width: 185px; }
        .digest { color: #666; font-family: Courier, monospace; font-size: 5.5px; margin-top: 3px; overflow-wrap: anywhere; }
        .status { font-weight: bold; margin-top: 4px; }
    </style>
</head>

<body>
    <div class="center">
        <div class="brand">{{ $emisor['nombre_comercial'] ?: $emisor['razon_social'] }}</div>
        <div class="issuer-name">{{ $emisor['razon_social'] }}</div>
        <div>RUC {{ $emisor['ruc'] }}</div>
        @if ($emisor['direccion'])
            <div>{{ $emisor['direccion'] }}</div>
        @endif
        @if ($emisor['distrito'] || $emisor['provincia'] || $emisor['departamento'])
            <div>{{ collect([$emisor['distrito'], $emisor['provincia'], $emisor['departamento']])->filter()->join(' - ') }}</div>
        @endif

        <div class="document-type">{{ $tipoComprobante }} electrónica</div>
        <div class="document-number">{{ $comprobante->serie }}-{{ $comprobante->correlativo }}</div>
        <div class="status">SUNAT: {{ $comprobante->estado === 'ACEPTADO_CON_OBSERVACIONES' ? 'ACEPTADO CON OBSERVACIONES' : 'ACEPTADO' }}</div>
    </div>

    <div class="separator"></div>

    <table>
        <tr><td class="label">Emisión</td><td>{{ $comprobante->fecha_emision->format('d/m/Y') }}</td></tr>
        <tr><td class="label">Moneda</td><td>{{ $comprobante->moneda === 'PEN' ? 'Soles' : $comprobante->moneda }}</td></tr>
        <tr><td class="label">Pago</td><td>{{ str($comprobante->forma_pago)->lower()->headline() }}</td></tr>
    </table>

    <div class="separator"></div>
    <div class="section-title">Cliente</div>
    <div><strong>{{ $comprobante->receptor_razon_social }}</strong></div>
    <div>{{ $comprobante->receptor_numero_documento ?: 'Sin documento' }}</div>
    @if ($comprobante->receptor_direccion)
        <div>{{ $comprobante->receptor_direccion }}</div>
    @endif

    @if ($comprobante->referencia)
        <div class="separator"></div>
        <div class="section-title">Documento relacionado</div>
        <div>{{ $comprobante->referencia->serie }}-{{ $comprobante->referencia->correlativo }}</div>
        <div>{{ $comprobante->tipo_nota }} - {{ $comprobante->motivo_nota }}</div>
    @endif

    <div class="separator"></div>
    <div class="section-title">Detalle</div>

    @foreach ($comprobante->items as $item)
        <table class="item">
            <tr><td class="item-description" colspan="2">{{ $item->numero_orden }}. {{ $item->descripcion }}</td></tr>
            <tr>
                <td class="item-detail">
                    {{ rtrim(rtrim(number_format((float) $item->cantidad, 3, '.', ''), '0'), '.') }} {{ $item->unidad_medida }}
                    x {{ $simboloMoneda }} {{ number_format((float) $item->precio_unitario, 2) }}
                </td>
                <td class="item-detail right">{{ $simboloMoneda }} {{ number_format((float) $item->monto_valor_venta + (float) $item->monto_igv, 2) }}</td>
            </tr>
        </table>
    @endforeach

    <div class="separator"></div>

    <table class="totals">
        <tr><td>Op. gravada</td><td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_gravada, 2) }}</td></tr>
        @if ((float) $comprobante->op_exonerada > 0)
            <tr><td>Op. exonerada</td><td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_exonerada, 2) }}</td></tr>
        @endif
        @if ((float) $comprobante->op_inafecta > 0)
            <tr><td>Op. inafecta</td><td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_inafecta, 2) }}</td></tr>
        @endif
        @if ((float) $comprobante->total_descuentos > 0)
            <tr><td>Descuentos</td><td class="right">- {{ $simboloMoneda }} {{ number_format((float) $comprobante->total_descuentos, 2) }}</td></tr>
        @endif
        <tr><td>IGV</td><td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->total_igv, 2) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->total, 2) }}</td></tr>
    </table>

    <div class="words"><strong>SON:</strong> {{ $totalEnLetras }}</div>
    <div class="separator"></div>

    <div class="center">
        <img class="qr" src="data:image/png;base64,{{ $qrBase64 }}" alt="Código QR">
        <div class="legal"><strong>Representación impresa de la {{ str($tipoComprobante)->lower() }} electrónica</strong></div>
        <div class="legal">Código QR generado conforme a las especificaciones SUNAT.</div>
        <div class="digest">{{ $digestValue }}</div>
    </div>
</body>

</html>
