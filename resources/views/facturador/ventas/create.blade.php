@extends('facturador.layouts.app')

@section('title', 'Nueva venta')

@section('content')
    <div class="mb-4">
        <h1 class="fac-page-title">Nueva venta</h1>
        <p class="fac-page-subtitle">Emite en pocos pasos. Nosotros nos encargamos de SUNAT.</p>
    </div>

    @if ($errors->any())
        <div class="fac-alert fac-alert-danger mb-4" role="alert">
            <strong>Revisa estos datos:</strong>
            <ul class="mb-0 mt-2 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('facturador.ventas.store') }}" x-data="ventaRapida({
        series: @js($series->toArray()),
        clientesUrl: @js(route('facturador.buscar.clientes')),
        productosUrl: @js(route('facturador.buscar.productos')),
        tipo: @js(old('tipo', 'BOLETA')),
        serie: @js(old('serie', '')),
        receptorTipo: @js(old('receptor_tipo_documento', 'SIN_DOCUMENTO')),
        receptorNumero: @js(old('receptor_numero_documento', '')),
        receptorNombre: @js(old('receptor_razon_social', 'Cliente varios')),
        items: @js(old('items', [])),
    })" x-cloak>
        @csrf
        <input type="hidden" name="tipo" :value="tipo">
        <input type="hidden" name="serie" :value="serie">

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <section class="fac-card fac-card-pad mb-4">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
                        <div>
                            <h2 class="fac-section-title">Tipo de comprobante</h2>
                            <p class="fac-section-copy">Boleta para personas; Factura requiere RUC.</p>
                        </div>
                        <div class="fac-segment" role="group" aria-label="Tipo de comprobante">
                            <button type="button" :class="{ active: tipo === 'BOLETA' }"
                                @click="cambiarTipo('BOLETA')">Boleta</button>
                            <button type="button" :class="{ active: tipo === 'FACTURA' }"
                                @click="cambiarTipo('FACTURA')">Factura</button>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2 small text-secondary">
                        <i data-lucide="hash" style="width:16px"></i>
                        <span x-show="serie">Serie asignada:</span>
                        <select class="form-select form-select-sm w-auto" x-model="serie" aria-label="Serie">
                            <template x-for="opcion in (series[tipo] || [])" :key="opcion">
                                <option :value="opcion" x-text="opcion"></option>
                            </template>
                        </select>
                        <span x-show="!serie" class="text-danger">No hay una serie activa.</span>
                    </div>
                </section>

                <section class="fac-card fac-card-pad mb-4">
                    <div class="mb-4">
                        <h2 class="fac-section-title">Cliente</h2>
                        <p class="fac-section-copy">Búscalo si ya está guardado o escribe sus datos aquí mismo.</p>
                    </div>
                    <div class="position-relative mb-4">
                        <label class="form-label" for="buscar-cliente">Buscar por DNI, RUC o nombre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i data-lucide="search" style="width:17px"></i></span>
                            <input id="buscar-cliente" class="form-control" type="search" x-model="buscarCliente"
                                @input="buscarClientes" placeholder="Empieza a escribir..." autocomplete="off">
                        </div>
                        <div class="fac-search-results" x-show="clientes.length || buscandoClientes"
                            @click.outside="clientes = []">
                            <div class="p-3 text-secondary small" x-show="buscandoClientes">Buscando…</div>
                            <template x-for="cliente in clientes" :key="cliente.id">
                                <button type="button" class="fac-search-result" @click="elegirCliente(cliente)">
                                    <strong x-text="cliente.nombre"></strong>
                                    <span x-text="cliente.numero_documento"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="receptor-tipo">Documento</label>
                            <select id="receptor-tipo" class="form-select" name="receptor_tipo_documento"
                                x-model="receptorTipo" @change="if (receptorTipo === 'SIN_DOCUMENTO') receptorNumero = ''">
                                <template x-for="documento in documentos()" :key="documento.value">
                                    <option :value="documento.value" x-text="documento.label"></option>
                                </template>
                            </select>
                        </div>
                        <div class="col-md-8" x-show="receptorTipo !== 'SIN_DOCUMENTO'">
                            <label class="form-label" for="receptor-numero">Número</label>
                            <input id="receptor-numero" class="form-control" name="receptor_numero_documento"
                                x-model="receptorNumero" maxlength="15" placeholder="Número de documento">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="receptor-nombre"
                                x-text="tipo === 'FACTURA' ? 'Razón social' : 'Nombre del cliente'"></label>
                            <input id="receptor-nombre" class="form-control" name="receptor_razon_social"
                                x-model="receptorNombre" maxlength="255" required placeholder="Nombre o razón social">
                        </div>
                    </div>
                </section>

                <section class="fac-card fac-card-pad">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
                        <div>
                            <h2 class="fac-section-title">Productos y servicios</h2>
                            <p class="fac-section-copy">Usa un precio guardado o agrega una línea manual.</p>
                        </div>
                        <button
                            class="btn btn-fac-soft btn-sm d-inline-flex align-items-center justify-content-center gap-2"
                            type="button" @click="agregarManual()">
                            <i data-lucide="plus"></i> Línea manual
                        </button>
                    </div>

                    <div class="position-relative mb-4">
                        <div class="input-group">
                            <span class="input-group-text"><i data-lucide="package-search" style="width:17px"></i></span>
                            <input class="form-control" type="search" x-model="buscarProducto" @input="buscarProductos"
                                placeholder="Buscar producto o servicio guardado…" autocomplete="off">
                        </div>
                        <div class="fac-search-results" x-show="productos.length || buscandoProductos"
                            @click.outside="productos = []">
                            <div class="p-3 text-secondary small" x-show="buscandoProductos">Buscando…</div>
                            <template x-for="producto in productos" :key="producto.id">
                                <button type="button" class="fac-search-result d-flex justify-content-between gap-3"
                                    @click="agregarProducto(producto)">
                                    <span><strong x-text="producto.nombre"></strong><span
                                            x-text="producto.codigo || 'Sin código'"></span></span>
                                    <strong class="text-nowrap" x-text="`S/ ${producto.valor_unitario}`"></strong>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="d-grid gap-3">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="fac-line-item">
                                <div class="row g-3 align-items-end">
                                    <div class="col-12 col-md-5">
                                        <label class="form-label">Descripción</label>
                                        <input class="form-control" :name="`items[${index}][descripcion]`"
                                            x-model="item.descripcion" required placeholder="Producto o servicio">
                                        <input type="hidden" :name="`items[${index}][codigo_producto]`"
                                            :value="item.codigo_producto">
                                        <input type="hidden" :name="`items[${index}][unidad_medida]`"
                                            :value="item.unidad_medida">
                                    </div>
                                    <div class="col-4 col-md-2">
                                        <label class="form-label">Cantidad</label>
                                        <input class="form-control" type="number" min="0.001" step="0.001"
                                            :name="`items[${index}][cantidad]`" x-model.number="item.cantidad" required>
                                    </div>
                                    <div class="col-8 col-md-3">
                                        <label class="form-label">Valor sin IGV</label>
                                        <div class="input-group"><span class="input-group-text">S/</span><input
                                                class="form-control" type="number" min="0.01" step="0.01"
                                                :name="`items[${index}][valor_unitario]`" x-model="item.valor_unitario"
                                                required></div>
                                        <input type="hidden" :name="`items[${index}][descuento]`"
                                            :value="item.descuento">
                                    </div>
                                    <div class="col-12 col-md-2 text-md-end">
                                        <div class="small text-secondary mb-2">S/ <span
                                                x-text="dinero(baseItem(item) * 1.18)"></span></div>
                                        <button class="btn btn-fac-soft btn-icon" type="button"
                                            @click="eliminarItem(index)" :disabled="items.length === 1"
                                            aria-label="Eliminar línea">×</button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <aside class="fac-sticky-summary">
                    <div class="fac-total-box mb-3">
                        <div class="fac-total-row"><span>Subtotal</span><strong>S/ <span
                                    x-text="dinero(subtotal)"></span></strong></div>
                        <div class="fac-total-row"><span>IGV (18%)</span><strong>S/ <span
                                    x-text="dinero(igv)"></span></strong></div>
                        <div class="fac-total-main"><span>Total</span><strong>S/ <span
                                    x-text="dinero(total)"></span></strong></div>
                    </div>
                    <button class="btn btn-fac-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2"
                        type="submit" :disabled="!serie || total <= 0">
                        <i data-lucide="send"></i> Emitir comprobante
                    </button>
                    <p class="text-center text-secondary small mt-3 mb-0">La serie, correlativo, impuestos y envío a SUNAT
                        son automáticos.</p>
                </aside>
            </div>
        </div>
    </form>
@endsection
