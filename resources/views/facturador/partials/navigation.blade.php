@php
    $itemsNavegacion = [
        ['route' => 'facturador.inicio', 'match' => 'facturador.inicio', 'icon' => 'house', 'label' => 'Inicio'],
        [
            'route' => 'facturador.ventas.create',
            'match' => 'facturador.ventas.*',
            'icon' => 'circle-plus',
            'label' => 'Nueva venta',
        ],
        [
            'route' => 'facturador.comprobantes.index',
            'match' => 'facturador.comprobantes.*',
            'icon' => 'files',
            'label' => 'Comprobantes',
        ],
        [
            'route' => 'facturador.clientes.index',
            'match' => 'facturador.clientes.*',
            'icon' => 'users-round',
            'label' => 'Clientes',
        ],
        [
            'route' => 'facturador.productos.index',
            'match' => 'facturador.productos.*',
            'icon' => 'package-search',
            'label' => 'Productos / Servicios',
        ],
        ['route' => 'facturador.cuenta', 'match' => 'facturador.cuenta', 'icon' => 'user-cog', 'label' => 'Mi cuenta'],
    ];
@endphp

<a class="fac-brand" href="{{ route('facturador.inicio') }}">
    <span class="fac-brand-mark"><i data-lucide="receipt-text"></i></span>
    <span>
        <span class="fac-brand-name d-block">Facturador</span>
        <span class="fac-brand-caption d-block">Ventas sin complicaciones</span>
    </span>
</a>

<div class="fac-nav-label">Operación</div>
<nav class="nav flex-column fac-nav">
    @foreach ($itemsNavegacion as $item)
        <a class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
            <i data-lucide="{{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>

<div class="fac-sidebar-footer">
    <form method="POST" action="{{ route('facturador.logout') }}">
        @csrf
        <button class="nav-link border-0 bg-transparent w-100" type="submit">
            <i data-lucide="log-out"></i>
            <span>Cerrar sesión</span>
        </button>
    </form>
</div>
