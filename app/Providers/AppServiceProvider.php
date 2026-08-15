<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Por IP, no por API Key: corre antes de AutenticarApiKey (es
        // middleware del grupo global 'api', las rutas todavía no
        // resolvieron la key) — cubre el caso más importante, frenar fuerza
        // bruta contra el propio endpoint de autenticación. Ver
        // docs/06_SEGURIDAD.md.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'sin-ip');
        });
    }
}
