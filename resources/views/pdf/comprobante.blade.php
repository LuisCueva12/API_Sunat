<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 26px 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #171717;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            margin: 0;
        }

        .header {
            border-bottom: 1px solid #d8d8d8;
            padding-bottom: 15px;
        }

        .issuer {
            width: 58%;
            vertical-align: top;
        }

        .document {
            border: 1px solid #888;
            padding: 12px;
            text-align: center;
            vertical-align: top;
            width: 38%;
        }

        .document-type {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .document-number {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
        }

        .brand {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .muted {
            color: #5f5f5f;
        }

        .section {
            margin-top: 16px;
        }

        .section-title {
            background: #f2f2f2;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: .4px;
            padding: 6px 8px;
            text-transform: uppercase;
        }

        .info {
            padding: 8px;
            width: 100%;
        }

        .info td {
            padding: 2px 5px;
            vertical-align: top;
        }

        .label {
            color: #666;
            width: 18%;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        .items {
            margin-top: 8px;
        }

        .items th {
            background: #f2f2f2;
            border-bottom: 1px solid #cfcfcf;
            font-size: 8px;
            padding: 7px 5px;
            text-align: left;
            text-transform: uppercase;
        }

        .items td {
            border-bottom: 1px solid #ececec;
            padding: 7px 5px;
            vertical-align: top;
        }

        .right {
            text-align: right !important;
        }

        .center {
            text-align: center !important;
        }

        .totals-wrap {
            margin-left: 55%;
            margin-top: 10px;
            width: 45%;
        }

        .totals td {
            padding: 3px 5px;
        }

        .totals .grand td {
            border-top: 1.5px solid #171717;
            font-size: 12px;
            font-weight: bold;
            padding-top: 6px;
        }

        .words {
            background: #f7f7f7;
            margin-top: 12px;
            padding: 8px;
        }

        .qr-section {
            margin-top: 22px;
            page-break-inside: avoid;
        }

        .qr {
            width: 4.5cm;
            height: 4.5cm;
        }

        .qr-copy {
            padding-left: 14px;
            vertical-align: middle;
        }

        .digest {
            color: #666;
            font-family: Courier, monospace;
            font-size: 7px;
            overflow-wrap: anywhere;
        }

        .legal {
            font-size: 8px;
            margin-top: 7px;
        }

        .footer {
            border-top: 1px solid #e0e0e0;
            color: #666;
            font-size: 8px;
            margin-top: 15px;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>

<body>
    <table class="header">
        <tr>
            <td class="issuer">
                <div class="brand">{{ $emisor['nombre_comercial'] ?: $emisor['razon_social'] }}</div>
                <div><strong>{{ $emisor['razon_social'] }}</strong></div>
                <div>RUC {{ $emisor['ruc'] }}</div>
                @if ($emisor['direccion'])
                    <div class="muted">{{ $emisor['direccion'] }}</div>
                @endif
                @if ($emisor['distrito'] || $emisor['provincia'] || $emisor['departamento'])
                    <div class="muted">
                        {{ collect([$emisor['distrito'], $emisor['provincia'], $emisor['departamento']])->filter()->join(' · ') }}
                    </div>
                @endif
            </td>
            <td style="width:4%"></td>
            <td class="document">
                <div>RUC {{ $emisor['ruc'] }}</div>
                <div class="document-type">{{ $tipoComprobante }} electrónica</div>
                <div class="document-number">{{ $comprobante->serie }}-{{ $comprobante->correlativo }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Datos del comprobante</div>
        <table class="info">
            <tr>
                <td class="label">Fecha de emisión</td>
                <td>{{ $comprobante->fecha_emision->format('d/m/Y') }}</td>
                <td class="label">Moneda</td>
                <td>{{ $comprobante->moneda === 'PEN' ? 'Soles' : $comprobante->moneda }}</td>
            </tr>
            <tr>
                <td class="label">Forma de pago</td>
                <td>{{ str($comprobante->forma_pago)->lower()->headline() }}</td>
                <td class="label">Estado SUNAT</td>
                <td>{{ $comprobante->estado === 'ACEPTADO_CON_OBSERVACIONES' ? 'Aceptado con observaciones' : 'Aceptado' }}
                </td>
            </tr>
            @if ($comprobante->referencia)
                <tr>
                    <td class="label">Documento relacionado</td>
                    <td>{{ $comprobante->referencia->serie }}-{{ $comprobante->referencia->correlativo }}</td>
                    <td class="label">Motivo</td>
                    <td>{{ $comprobante->tipo_nota }} · {{ $comprobante->motivo_nota }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Cliente</div>
        <table class="info">
            <tr>
                <td class="label">Nombre / Razón social</td>
                <td>{{ $comprobante->receptor_razon_social }}</td>
            </tr>
            <tr>
                <td class="label">Documento</td>
                <td>{{ $comprobante->receptor_numero_documento ?: 'Sin documento' }}</td>
            </tr>
            @if ($comprobante->receptor_direccion)
                <tr>
                    <td class="label">Dirección</td>
                    <td>{{ $comprobante->receptor_direccion }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">Detalle</div>
        <table class="items">
            <thead>
                <tr>
                    <th style="width:7%">Ítem</th>
                    <th style="width:43%">Descripción</th>
                    <th class="center" style="width:10%">Unidad</th>
                    <th class="right" style="width:10%">Cantidad</th>
                    <th class="right" style="width:15%">Precio unit.</th>
                    <th class="right" style="width:15%">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($comprobante->items as $item)
                    <tr>
                        <td>{{ $item->numero_orden }}</td>
                        <td>{{ $item->descripcion }}</td>
                        <td class="center">{{ $item->unidad_medida }}</td>
                        <td class="right">
                            {{ rtrim(rtrim(number_format((float) $item->cantidad, 3, '.', ''), '0'), '.') }}</td>
                        <td class="right">{{ number_format((float) $item->precio_unitario, 2) }}</td>
                        <td class="right">
                            {{ number_format((float) $item->monto_valor_venta + (float) $item->monto_igv, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="totals-wrap">
        <table class="totals">
            <tr>
                <td>Op. gravada</td>
                <td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_gravada, 2) }}</td>
            </tr>
            @if ((float) $comprobante->op_exonerada > 0)
                <tr>
                    <td>Op. exonerada</td>
                    <td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_exonerada, 2) }}
                    </td>
                </tr>
            @endif
            @if ((float) $comprobante->op_inafecta > 0)
                <tr>
                    <td>Op. inafecta</td>
                    <td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->op_inafecta, 2) }}
                    </td>
                </tr>
            @endif
            @if ((float) $comprobante->total_descuentos > 0)
                <tr>
                    <td>Descuentos</td>
                    <td class="right">- {{ $simboloMoneda }}
                        {{ number_format((float) $comprobante->total_descuentos, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td>IGV</td>
                <td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->total_igv, 2) }}</td>
            </tr>
            <tr class="grand">
                <td>Total</td>
                <td class="right">{{ $simboloMoneda }} {{ number_format((float) $comprobante->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="words"><strong>SON:</strong> {{ $totalEnLetras }}</div>

    <table class="qr-section">
        <tr>
            <td style="width:4.8cm"><img class="qr" src="data:image/png;base64,{{ $qrBase64 }}"
                    alt="Código QR"></td>
            <td class="qr-copy">
                <strong>Representación impresa de la {{ str($tipoComprobante)->lower() }} electrónica</strong>
                <div class="legal">Código QR generado con la información fiscal del comprobante conforme a las
                    especificaciones SUNAT.</div>
                <div class="legal"><strong>Valor resumen:</strong></div>
                <div class="digest">{{ $digestValue }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">Documento generado por el sistema de emisión electrónica del contribuyente.</div>
</body>

</html>
