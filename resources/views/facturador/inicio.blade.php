@extends('facturador.layouts.app')

@section('title', 'Inicio')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4">
        <div>
            <h1 class="fac-page-title">Hola, {{ str(auth()->user()->name)->before(' ') }}</h1>
            <p class="fac-page-subtitle">Todo listo para tu siguiente venta.</p>
        </div>
        <a class="btn btn-fac-primary d-inline-flex align-items-center justify-content-center gap-2"
            href="{{ route('facturador.ventas.create') }}">
            <i data-lucide="plus"></i> Nueva venta
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="fac-card fac-stat h-100">
                <div class="fac-stat-label">Ventas de hoy</div>
                <div class="fac-stat-value">{{ $ventasHoy }}</div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="fac-card fac-stat h-100">
                <div class="fac-stat-label">Total de hoy</div>
                <div class="fac-stat-value">S/ {{ number_format((float) $totalHoy, 2) }}</div>
            </div>
        </div>
    </div>

    <section class="fac-card overflow-hidden">
        <div class="fac-card-pad d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fac-section-title">Comprobantes recientes</h2>
                <p class="fac-section-copy">Tus últimas operaciones, sin ruido.</p>
            </div>
            <a class="btn btn-fac-soft btn-sm" href="{{ route('facturador.comprobantes.index') }}">Ver todos</a>
        </div>
        @if ($recientes->isEmpty())
            <div class="fac-divider fac-empty">
                <div class="fac-empty-icon"><i data-lucide="receipt"></i></div>
                <h3 class="fac-section-title">Aún no hay ventas</h3>
                <p class="fac-section-copy mb-3">Tu primer comprobante aparecerá aquí.</p>
                <a class="btn btn-fac-primary" href="{{ route('facturador.ventas.create') }}">Crear primera venta</a>
            </div>
        @else
            <div class="table-responsive fac-divider">
                <table class="table fac-table">
                    <tbody>
                        @foreach ($recientes as $comprobante)
                            <tr>
                                <td><a class="fac-doc-number"
                                        href="{{ route('facturador.comprobantes.show', $comprobante->id) }}">{{ $comprobante->serie }}-{{ str_pad((string) $comprobante->correlativo, 8, '0', STR_PAD_LEFT) }}</a>
                                </td>
                                <td>{{ $comprobante->receptor_razon_social }}</td>
                                <td class="text-end fw-semibold">S/ {{ number_format((float) $comprobante->total, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
