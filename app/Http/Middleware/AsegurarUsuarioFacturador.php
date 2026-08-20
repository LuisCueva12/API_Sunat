<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AsegurarUsuarioFacturador
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('web');

        if (! $usuario instanceof Usuario || $usuario->empresa_id === null) {
            return redirect()->guest(route('facturador.login'));
        }

        return $next($request);
    }
}
