<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Api\RespuestaApi;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class Idempotencia
{
    private const CODIGO_UNIQUE_VIOLATION = '23505';

    public function handle(Request $request, Closure $next): Response
    {
        $clave = $request->header('Idempotency-Key');

        if ($clave === null || $clave === '') {
            return $next($request);
        }

        $empresaId = $request->attributes->get('empresa_id');
        $endpoint = $request->path();
        $hashSolicitud = hash('sha256', (string) $request->getContent());

        $existente = DB::table('idempotency_keys')
            ->where('empresa_id', $empresaId)
            ->where('endpoint', $endpoint)
            ->where('clave', $clave)
            ->first();

        if ($existente !== null && Carbon::parse($existente->expira_at)->isFuture()) {
            return $this->responderDesdeExistente($existente, $hashSolicitud);
        }

        if ($existente !== null) {
            DB::table('idempotency_keys')
                ->where('empresa_id', $empresaId)
                ->where('endpoint', $endpoint)
                ->where('clave', $clave)
                ->delete();
        }

        try {
            DB::table('idempotency_keys')->insert([
                'empresa_id' => $empresaId,
                'clave' => $clave,
                'endpoint' => $endpoint,
                'hash_solicitud' => $hashSolicitud,
                'estado' => 'PROCESANDO',
                'expira_at' => now()->addHours((int) config('facturacion.idempotencia.ttl_horas')),
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== self::CODIGO_UNIQUE_VIOLATION) {
                throw $e;
            }

            return RespuestaApi::error(
                'SOLICITUD_EN_PROCESO',
                'Ya hay una solicitud en curso con esta Idempotency-Key.',
                409,
            );
        }

        $response = $next($request);

        DB::table('idempotency_keys')
            ->where('empresa_id', $empresaId)
            ->where('endpoint', $endpoint)
            ->where('clave', $clave)
            ->update([
                'estado' => 'COMPLETADO',
                'respuesta_cache' => json_encode([
                    'status' => $response->getStatusCode(),
                    'body' => json_decode($response->getContent() ?: 'null', true),
                ]),
            ]);

        return $response;
    }

    private function responderDesdeExistente(object $existente, string $hashSolicitud): Response
    {
        if ($existente->hash_solicitud !== $hashSolicitud) {
            return RespuestaApi::error(
                'IDEMPOTENCY_KEY_CONFLICTO',
                'Esta Idempotency-Key ya se usó con una solicitud de contenido diferente.',
                422,
            );
        }

        if ($existente->estado === 'PROCESANDO') {
            return RespuestaApi::error(
                'SOLICITUD_EN_PROCESO',
                'Ya hay una solicitud en curso con esta Idempotency-Key.',
                409,
            );
        }

        $cache = json_decode((string) $existente->respuesta_cache, true);

        return response()->json($cache['body'], $cache['status']);
    }
}
