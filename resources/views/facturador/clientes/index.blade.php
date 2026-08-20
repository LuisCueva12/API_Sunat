@extends('facturador.layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="mb-4">
        <h1 class="fac-page-title">Clientes</h1>
        <p class="fac-page-subtitle">Encuentra rápidamente a quienes ya atendiste.</p>
    </div>
    <section class="fac-card overflow-hidden">
        <form class="fac-card-pad" method="GET">
            <div class="input-group"><span class="input-group-text"><i data-lucide="search" style="width:17px"></i></span><input
                    class="form-control" name="q" value="{{ request('q') }}"
                    placeholder="Buscar por nombre, DNI o RUC"><button class="btn btn-fac-soft"
                    type="submit">Buscar</button></div>
        </form>
        @if ($clientes->isEmpty())
            <div class="fac-divider fac-empty">
                <div class="fac-empty-icon"><i data-lucide="users-round"></i></div>
                <h2 class="fac-section-title">No hay clientes para mostrar</h2>
                <p class="fac-section-copy">No necesitas crearlos antes de vender. Puedes escribir sus datos directamente en
                    Nueva venta.</p>
            </div>
        @else
            <div class="table-responsive fac-divider fac-table-wrap">
                <table class="table fac-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th class="fac-hide-mobile">Contacto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clientes as $cliente)
                            <tr>
                                <td class="fw-semibold">{{ $cliente->razon_social }}</td>
                                <td>{{ $cliente->numero_documento }}</td>
                                <td class="fac-hide-mobile text-secondary">{{ $cliente->email ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($clientes->hasPages())
                <div class="fac-card-pad fac-divider">{{ $clientes->links() }}</div>
            @endif
        @endif
    </section>
@endsection
