@extends('facturador.layouts.app')

@section('title', 'Comprobantes')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
        <div>
            <h1 class="fac-page-title">Comprobantes</h1>
            <p class="fac-page-subtitle">Consulta tus ventas y su estado real en SUNAT.</p>
        </div>
        <a class="btn btn-fac-primary" href="{{ route('facturador.ventas.create') }}"><i data-lucide="plus"></i> Nueva
            venta</a>
    </div>

    <section class="fac-card overflow-hidden">
        <form class="fac-card-pad" method="GET">
            <div class="row g-2">
                <div class="col-md-8">
                    <div class="input-group"><span class="input-group-text"><i data-lucide="search"
                                style="width:17px"></i></span><input class="form-control" name="q"
                            value="{{ request('q') }}" placeholder="Buscar por cliente, documento o número"></div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="estado">
                        <option value="">Todos los estados</option>
                        @foreach (['ACEPTADO' => 'Aceptados', 'PROCESANDO' => 'Procesando', 'REGISTRADO' => 'Preparando', 'RECHAZADO' => 'No aceptados', 'ERROR' => 'Con inconvenientes'] as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected(request('estado') === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1"><button class="btn btn-fac-soft w-100 h-100" aria-label="Buscar"><i
                            data-lucide="arrow-right"></i></button></div>
            </div>
        </form>

        @if ($comprobantes->isEmpty())
            <div class="fac-divider fac-empty">
                <div class="fac-empty-icon"><i data-lucide="files"></i></div>
                <h2 class="fac-section-title">No encontramos comprobantes</h2>
                <p class="fac-section-copy">Prueba otra búsqueda o crea una nueva venta.</p>
            </div>
        @else
            <div class="table-responsive fac-divider fac-table-wrap">
                <table class="table fac-table">
                    <thead>
                        <tr>
                            <th>Comprobante</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comprobantes as $comprobante)
                            @php
                                $claseEstado = match ($comprobante->estado) {
                                    'ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES' => 'fac-badge-success',
                                    'RECHAZADO', 'ERROR' => 'fac-badge-danger',
                                    default => 'fac-badge-warning',
                                };
                                $etiquetaEstado = match ($comprobante->estado) {
                                    'ACEPTADO' => 'Aceptado',
                                    'ACEPTADO_CON_OBSERVACIONES' => 'Aceptado',
                                    'RECHAZADO' => 'No aceptado',
                                    'ERROR' => 'Revisar',
                                    'PROCESANDO' => 'Enviando',
                                    default => 'Preparando',
                                };
                            @endphp
                            <tr>
                                <td><a class="fac-doc-number"
                                        href="{{ route('facturador.comprobantes.show', $comprobante->id) }}">{{ $comprobante->serie }}-{{ str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT) }}</a>
                                    <div class="small text-secondary">{{ str($comprobante->tipo)->headline() }}</div>
                                </td>
                                <td>{{ $comprobante->receptor_razon_social }}<div class="small text-secondary">
                                        {{ $comprobante->receptor_numero_documento ?: 'Sin documento' }}</div>
                                </td>
                                <td>{{ $comprobante->fecha_emision->format('d/m/Y') }}</td>
                                <td><span class="fac-badge {{ $claseEstado }}">{{ $etiquetaEstado }}</span></td>
                                <td class="text-end fw-semibold">S/ {{ number_format((float) $comprobante->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($comprobantes->hasPages())
                <div class="fac-card-pad fac-divider">{{ $comprobantes->links() }}</div>
            @endif
        @endif
    </section>
@endsection
