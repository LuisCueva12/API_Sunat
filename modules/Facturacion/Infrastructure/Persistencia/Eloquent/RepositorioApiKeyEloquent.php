<?php

declare(strict_types=1);

namespace Modules\Facturacion\Infrastructure\Persistencia\Eloquent;

use App\Models\ApiKey as ApiKeyEloquent;
use Modules\Facturacion\Domain\Empresa\ApiKeyEmpresa;
use Modules\Facturacion\Domain\Puertos\RepositorioApiKey;

final class RepositorioApiKeyEloquent implements RepositorioApiKey
{
    public function guardar(ApiKeyEmpresa $apiKey): void
    {
        ApiKeyEloquent::query()->updateOrCreate(
            ['id' => $apiKey->id()],
            [
                'empresa_id' => $apiKey->empresaId(),
                'nombre' => $apiKey->nombre(),
                'prefijo' => $apiKey->prefijo(),
                'hash' => $apiKey->hash(),
                'scopes' => $apiKey->scopes(),
                'expira_at' => $apiKey->expiraEn(),
                'estado' => $apiKey->estado()->value,
            ],
        );
    }
}
