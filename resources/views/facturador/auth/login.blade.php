<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Facturador</title>
    @vite(['resources/css/facturador.css', 'resources/js/facturador.js'])
</head>

<body class="facturador-body">
    <main class="fac-login">
        <section class="fac-card fac-login-card">
            <div class="fac-brand px-0 pb-4">
                <span class="fac-brand-mark"><i data-lucide="receipt-text"></i></span>
                <span>
                    <span class="fac-brand-name d-block">Facturador</span>
                    <span class="fac-brand-caption d-block">Facturación electrónica simple</span>
                </span>
            </div>
            <h1 class="fac-page-title fs-3">Bienvenido</h1>
            <p class="fac-page-subtitle mb-4">Ingresa para comenzar a vender.</p>

            @if ($errors->any())
                <div class="fac-alert fac-alert-danger mb-3">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('facturador.login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-control" id="email" name="email" type="email" value="{{ old('email') }}"
                        autocomplete="email" autofocus required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control" id="password" name="password" type="password"
                        autocomplete="current-password" required>
                </div>
                <div class="form-check mb-4">
                    <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                    <label class="form-check-label small text-secondary" for="remember">Mantener mi sesión</label>
                </div>
                <button class="btn btn-fac-primary w-100" type="submit">Ingresar</button>
            </form>
        </section>
    </main>
</body>

</html>
