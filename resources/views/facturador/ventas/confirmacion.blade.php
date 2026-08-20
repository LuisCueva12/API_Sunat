@extends('facturador.layouts.app')

@section('title', 'Venta emitida')

@section('content')
    <section class="fac-card fac-card-pad fac-confirm" x-data="seguimientoVenta(@js(route('facturador.ventas.estado', $comprobante->id)), @js($comprobante->estado))">
        <div class="fac-confirm-icon">
            <i data-lucide="check"></i>
        </div>
        <div class="text-uppercase small text-secondary fw-semibold mb-2">Comprobante emitido</div>
        <h1 class="fac-confirm-number">
            {{ $comprobante->serie }}-{{ str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT) }}</h1>
        <div class="mt-3">
            <span class="fac-badge"
                :class="{
                    'fac-badge-success': ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'].includes(estado),
                    'fac-badge-danger': ['RECHAZADO', 'ERROR'].includes(estado),
                    'fac-badge-warning': !terminal
                }"
                x-text="etiqueta"></span>
        </div>

        <div class="fac-confirm-total">S/ {{ number_format((float) $comprobante->total, 2) }}</div>
        <div class="text-secondary mt-1">{{ $comprobante->receptor_razon_social }}</div>

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4 fac-no-print">
            <a class="btn btn-fac-soft" href="{{ route('facturador.comprobantes.show', $comprobante->id) }}"><i
                    data-lucide="eye"></i> Ver detalle</a>
            <button class="btn btn-fac-soft" type="button" onclick="window.print()"><i data-lucide="printer"></i>
                Imprimir</button>
            <button class="btn btn-fac-soft" type="button"
                onclick="navigator.share ? navigator.share({title: 'Comprobante', url: window.location.href}) : navigator.clipboard.writeText(window.location.href)"><i
                    data-lucide="share-2"></i> Enviar</button>
            <a class="btn btn-fac-primary" href="{{ route('facturador.ventas.create') }}"><i data-lucide="plus"></i> Nueva
                venta</a>
        </div>
        <p class="small text-secondary mt-4 mb-0" x-show="!terminal">Puedes continuar trabajando. Actualizaremos el estado
            automáticamente.</p>
        <p class="small text-secondary mt-4 mb-0" x-show="['ACEPTADO','ACEPTADO_CON_OBSERVACIONES'].includes(estado)">El PDF
            estará disponible cuando se habilite la generación de representaciones impresas.</p>
    </section>
@endsection
