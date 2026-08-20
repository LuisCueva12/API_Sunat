@extends('facturador.layouts.app')

@section('title', 'Productos y servicios')

@section('content')
    <div x-data="{ creando: {{ $errors->any() ? 'true' : 'false' }}, tipoProducto: @js(old('tipo', 'SERVICIO')) }">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3 mb-4">
            <div>
                <h1 class="fac-page-title">Productos y servicios</h1>
                <p class="fac-page-subtitle">Guarda lo que vendes con su precio habitual.</p>
            </div>
            <button class="btn btn-fac-primary" type="button" @click="creando = !creando"><i data-lucide="plus"></i>
                Agregar</button>
        </div>

        <section class="fac-card fac-card-pad mb-4" x-show="creando" x-transition x-cloak>
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="fac-section-title">Nuevo producto o servicio</h2>
                    <p class="fac-section-copy">El precio se recuperará automáticamente en Nueva venta.</p>
                </div><button class="btn btn-fac-soft btn-icon" @click="creando = false" type="button">×</button>
            </div>
            @if ($errors->any())
                <div class="fac-alert fac-alert-danger mb-3">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('facturador.productos.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-5"><label class="form-label">Nombre</label><input class="form-control" name="nombre"
                            value="{{ old('nombre') }}" required placeholder="Ej. Consulta profesional"></div>
                    <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="tipo"
                            x-model="tipoProducto">
                            <option value="SERVICIO" @selected(old('tipo') === 'SERVICIO')>Servicio</option>
                            <option value="PRODUCTO" @selected(old('tipo') === 'PRODUCTO')>Producto</option>
                        </select></div>
                    <div class="col-md-2"><label class="form-label">Código</label><input class="form-control" name="codigo"
                            value="{{ old('codigo') }}" placeholder="Opcional"></div>
                    <div class="col-md-2"><label class="form-label">Valor sin IGV</label><input class="form-control"
                            type="number" min="0.01" step="0.01" name="valor_unitario"
                            value="{{ old('valor_unitario') }}" required></div>
                    <input type="hidden" name="unidad_medida" :value="tipoProducto === 'SERVICIO' ? 'ZZ' : 'NIU'">
                    <div class="col-12"><button class="btn btn-fac-primary" type="submit">Guardar</button></div>
                </div>
            </form>
        </section>

        <section class="fac-card overflow-hidden">
            <form class="fac-card-pad" method="GET">
                <div class="input-group"><span class="input-group-text"><i data-lucide="search"
                            style="width:17px"></i></span><input class="form-control" name="q"
                        value="{{ request('q') }}" placeholder="Buscar producto, servicio o código"><button
                        class="btn btn-fac-soft">Buscar</button></div>
            </form>
            @if ($productos->isEmpty())
                <div class="fac-divider fac-empty">
                    <div class="fac-empty-icon"><i data-lucide="package-search"></i></div>
                    <h2 class="fac-section-title">Tu catálogo está vacío</h2>
                    <p class="fac-section-copy mb-3">Puedes agregar productos ahora o escribirlos manualmente al vender.</p>
                    <button class="btn btn-fac-primary" type="button" @click="creando = true">Agregar el primero</button>
                </div>
            @else
                <div class="table-responsive fac-divider fac-table-wrap">
                    <table class="table fac-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Código</th>
                                <th class="text-end">Valor sin IGV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($productos as $producto)
                                <tr>
                                    <td class="fw-semibold">{{ $producto->nombre }}</td>
                                    <td>{{ str($producto->tipo)->lower()->ucfirst() }}</td>
                                    <td class="text-secondary">{{ $producto->codigo ?: '—' }}</td>
                                    <td class="text-end fw-semibold">S/
                                        {{ number_format((float) $producto->valor_unitario, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($productos->hasPages())
                    <div class="fac-card-pad fac-divider">{{ $productos->links() }}</div>
                @endif
            @endif
        </section>
    </div>
@endsection
