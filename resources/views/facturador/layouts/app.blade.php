<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Facturador') · {{ config('app.name') }}</title>
    @vite(['resources/css/facturador.css', 'resources/js/facturador.js'])
</head>

<body class="facturador-body">
    <div class="fac-shell">
        <aside class="fac-sidebar">
            @include('facturador.partials.navigation')
        </aside>

        <div class="offcanvas offcanvas-start fac-offcanvas" tabindex="-1" id="facMobileMenu"
            aria-labelledby="facMobileMenuLabel">
            <div class="offcanvas-header px-4 pt-4 pb-2">
                <span class="visually-hidden" id="facMobileMenuLabel">Navegación</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas"
                    aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body px-3 pt-0 position-relative">
                @include('facturador.partials.navigation')
            </div>
        </div>

        <main class="fac-main">
            <header class="fac-topbar d-flex align-items-center px-3 px-lg-4">
                <button class="btn btn-fac-soft btn-icon fac-mobile-bar me-3" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#facMobileMenu" aria-controls="facMobileMenu" aria-label="Abrir menú">
                    <i data-lucide="menu"></i>
                </button>
                <div class="d-lg-none fw-bold">Facturador</div>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <div class="text-end d-none d-sm-block">
                        <div class="small fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-secondary">{{ auth()->user()->email }}</div>
                    </div>
                    <a href="{{ route('facturador.cuenta') }}" class="btn btn-fac-soft btn-icon" aria-label="Mi cuenta">
                        <i data-lucide="user-round"></i>
                    </a>
                </div>
            </header>

            <div class="fac-content">
                @if (session('success'))
                    <div class="fac-alert fac-alert-success mb-4" role="status">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="fac-alert fac-alert-danger mb-4" role="alert">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>

</html>
