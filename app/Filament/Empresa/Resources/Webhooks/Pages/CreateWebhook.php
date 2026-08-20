<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Webhooks\Pages;

use App\Filament\Empresa\Resources\Webhooks\WebhookResource;
use App\Models\Usuario;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

final class CreateWebhook extends CreateRecord
{
    protected static string $resource = WebhookResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();
        $data['empresa_id'] = $usuario->empresa_id;

        return $data;
    }
}
