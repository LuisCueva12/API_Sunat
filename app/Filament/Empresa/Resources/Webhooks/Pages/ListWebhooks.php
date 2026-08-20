<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Webhooks\Pages;

use App\Filament\Empresa\Resources\Webhooks\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListWebhooks extends ListRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
