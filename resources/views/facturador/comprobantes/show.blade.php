@extends('facturador.layouts.app')

@section('title', $comprobante->serie . '-' . $comprobante->correlativo)

@section('content')
    @php
        $estadoVisible = match ($comprobante->estado) {
            'ACEPTADO' => ['Aceptado por SUNAT', 'fac-badge-success'],
            'ACEPTADO_CON_OBSERVACIONES' => ['Aceptado con observaciones', 'fac-badge-warning'],
            'RECHAZADO' => ['SUNAT no aceptó el comprobante', 'fac-badge-danger'],
            'ERROR' => ['No se pudo completar el envío', 'fac-badge-danger'],
            'PROCESANDO' => ['Enviando a SUNAT', 'fac-badge-warning'],
            default => ['Preparando envío', 'fac-badge-neutral'],
        };
    @endphp
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div><a class="small text-secondary text-decoration-none" href="{{ route('facturador.comprobantes.index') }}">←
                Comprobantes</a>
            <h1 class="fac-page-title mt-2">
                {{ $comprobante->serie }}-{{ str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT) }}</h1>
            <p class="fac-page-subtitle">{{ str($comprobante->tipo)->headline() }} ·
                {{ $comprobante->fecha_emision->format('d/m/Y') }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 align-self-start">
            <span class="fac-badge {{ $estadoVisible[1] }}">{{ $estadoVisible[0] }}</span>
            <button class="btn btn-fac-soft fac-no-print" onclick="window.print()"><i data-lucide="printer"></i>
                Imprimir</button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <section class="fac-card overflow-hidden">
                <div class="fac-card-pad">
                    <h2 class="fac-section-title">Detalle de la venta</h2>
                    <p class="fac-section-copy">{{ $comprobante->receptor_razon_social }} ·
                        {{ $comprobante->receptor_numero_documento ?: 'Sin documento' }}</p>
                </div>
                <div class="table-responsive fac-divider">
                    <table class="table fac-table">
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th>Cantidad</th>
                                <th class="text-end">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($comprobante->items as $item)
                                <tr>
                                    <td>{{ $item->descripcion }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $item->cantidad, 3), '0'), '.') }}</td>
                                    <td class="text-end">S/
                                        {{ number_format((float) $item->precio_unitario * (float) $item->cantidad, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-lg-4">
            <aside class="fac-card fac-card-pad">
                <div class="fac-total-row text-secondary"><span>Subtotal</span><strong>S/
                        {{ number_format((float) $comprobante->op_gravada, 2) }}</strong></div>
                <div class="fac-total-row text-secondary"><span>IGV</span><strong>S/
                        {{ number_format((float) $comprobante->total_igv, 2) }}</strong></div>
                <div class="fac-total-main text-dark border-top"><span>Total</span><strong>S/
                        {{ number_format((float) $comprobante->total, 2) }}</strong></div>
            </aside>
        </div>
    </div>
@endsection
