<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Webhooks\Pages;

use App\Filament\Empresa\Resources\Webhooks\WebhookResource;
use Filament\Resources\Pages\EditRecord;

final class EditWebhook extends EditRecord
{
    protected static string $resource = WebhookResource::class;
}
