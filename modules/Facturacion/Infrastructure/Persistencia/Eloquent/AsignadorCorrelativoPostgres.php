<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use Illuminate\Support\Facades\DB;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\Puertos\AsignadorCorrelativo;
use Modules\Facturacion\Domain\ValueObjects\NumeroComprobante;
use Modules\Facturacion\Domain\ValueObjects\Serie;

/**
 * Usa el query builder (no Eloquent) deliberadamente: esta es la operación
 * más sensible a concurrencia de toda la plataforma y no necesita hidratar
 * modelos — solo un SELECT ... FOR UPDATE + UPDATE dentro de una
 * transacción corta. El UNIQUE (empresa_id, tipo, serie, correlativo) en
 * comprobantes es la segunda barrera si esto llegara a fallar.
 */
final class AsignadorCorrelativoPostgres implements AsignadorCorrelativo
{
    public function asignar(string $empresaId, TipoComprobante $tipo, Serie $serie): NumeroComprobante
    {
        return DB::transaction(function () use ($empresaId, $tipo, $serie) {
            $fila = DB::table('series')
                ->where('empresa_id', $empresaId)
                ->where('tipo_comprobante', $tipo->value)
                ->where('serie', $serie->valor())
                ->lockForUpdate()
                ->first();

            if ($fila === null) {
                throw new SerieInvalidaException(
                    "No existe la serie {$serie->valor()} de tipo {$tipo->value} para la empresa {$empresaId}."
                );
            }

            // DB::table() (query builder puro) no castea columnas boolean
            // como Eloquent: pdo_pgsql puede devolver el nativo bool o el
            // string 't'/'f' según versión/driver — un "! $fila->activa"
            // directo es un bug real si llega como 'f' (string no vacío,
            // truthy en PHP). Normalizar explícitamente, nunca confiar en
            // la coerción de PHP para un valor de origen ambiguo.
            if (! $this->esVerdadero($fila->activa)) {
                throw new SerieInvalidaException("La serie {$serie->valor()} está inactiva.");
            }

            $nuevoCorrelativo = (int) $fila->correlativo_actual + 1;

            DB::table('series')
                ->where('id', $fila->id)
                ->update([
                    'correlativo_actual' => $nuevoCorrelativo,
                    'updated_at' => now(),
                ]);

            return new NumeroComprobante($serie, $nuevoCorrelativo);
        });
    }

    private function esVerdadero(mixed $valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        return in_array($valor, ['t', '1', 1, true], true);
    }
}
