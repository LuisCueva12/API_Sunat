<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Modules\Facturacion\Domain\Empresa\ScopeApi;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?? 'sin-ip');
        });

        Passport::tokensCan([
            ScopeApi::ComprobantesCrear->value => 'Emitir facturas, boletas, notas de crédito y notas de débito.',
            ScopeApi::ComprobantesLeer->value => 'Consultar comprobantes y su estado.',
            ScopeApi::ComprobantesReintentar->value => 'Reintentar el envío de un comprobante en error.',
        ]);

        Passport::clientCredentialsTokensExpireIn(now()->addHour());
    }
}
