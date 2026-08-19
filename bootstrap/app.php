<?php

declare(strict_types=1);

use App\Http\Api\RespuestaApi;
use App\Http\Middleware\AsignarRequestId;
use App\Http\Middleware\AutenticarApiKey;
use App\Http\Middleware\Idempotencia;
use App\Http\Middleware\VerificarScopeApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Facturacion\Domain\Excepciones\ComprobanteInvalidoException;
use Modules\Facturacion\Domain\Excepciones\ConfiguracionSunatInvalidaException;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [AsignarRequestId::class]);
        $middleware->throttleApi();

        $middleware->alias([
            'api.key' => AutenticarApiKey::class,
            'api.scope' => VerificarScopeApiKey::class,
            'api.idempotencia' => Idempotencia::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return RespuestaApi::error(
                'DATOS_INVALIDOS',
                'Los datos enviados contienen errores.',
                422,
                collect($e->errors())->map(fn ($mensajes, $campo) => ['campo' => $campo, 'mensajes' => $mensajes])->values()->all(),
            );
        });

        $exceptions->render(function (ComprobanteInvalidoException|SerieInvalidaException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return RespuestaApi::error('COMPROBANTE_INVALIDO', $e->getMessage(), 422);
        });

        $exceptions->render(function (ConfiguracionSunatInvalidaException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return RespuestaApi::error('EMPRESA_NO_CONFIGURADA', $e->getMessage(), 422);
        });

        $exceptions->render(function (TransicionEstadoInvalidaException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return RespuestaApi::error('TRANSICION_INVALIDA', $e->getMessage(), 409);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return RespuestaApi::error('NO_ENCONTRADO', 'El recurso solicitado no existe.', 404);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface) {
                return RespuestaApi::error('ERROR_HTTP', $e->getMessage(), $e->getStatusCode());
            }

            Log::error('Error no controlado en la API', [
                'excepcion' => $e::class,
                'mensaje' => $e->getMessage(),
                'request_id' => $request->attributes->get('request_id'),
            ]);

            return RespuestaApi::error('ERROR_INTERNO', 'Ocurrió un error inesperado. Intenta nuevamente.', 500);
        });
    })->create();
