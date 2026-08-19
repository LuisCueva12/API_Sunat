<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\Serie as SerieEloquent;
use Illuminate\Database\QueryException;
use Modules\Facturacion\Domain\Comprobante\TipoComprobante;
use Modules\Facturacion\Domain\Empresa\SerieEmpresa;
use Modules\Facturacion\Domain\Excepciones\SerieInvalidaException;
use Modules\Facturacion\Domain\Puertos\RepositorioSerie;
use Modules\Facturacion\Domain\ValueObjects\Serie;

final class RepositorioSerieEloquent implements RepositorioSerie
{
    private const CODIGO_UNIQUE_VIOLATION = '23505';

    public function guardar(SerieEmpresa $serie): void
    {
        try {
            SerieEloquent::query()->updateOrCreate(
                ['id' => $serie->id()],
                [
                    'empresa_id' => $serie->empresaId(),
                    'tipo_comprobante' => $serie->tipoComprobante()->value,
                    'serie' => $serie->serie()->valor(),
                    'activa' => $serie->estaActiva(),
                ],
            );
        } catch (QueryException $e) {
            if ($e->getCode() === self::CODIGO_UNIQUE_VIOLATION) {
                throw new SerieInvalidaException(
                    "Ya existe la serie {$serie->serie()->valor()} de tipo {$serie->tipoComprobante()->value} para esta empresa."
                );
            }

            throw $e;
        }
    }

    public function existe(string $empresaId, TipoComprobante $tipo, Serie $serie): bool
    {
        return SerieEloquent::query()
            ->where('empresa_id', $empresaId)
            ->where('tipo_comprobante', $tipo->value)
            ->where('serie', $serie->valor())
            ->exists();
    }
}
