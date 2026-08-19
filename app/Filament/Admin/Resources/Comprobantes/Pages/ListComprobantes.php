<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Comprobantes\Pages;

use App\Filament\Admin\Resources\Comprobantes\ComprobanteResource;
use Filament\Resources\Pages\ListRecords;

final class ListComprobantes extends ListRecords
{
    protected static string $resource = ComprobanteResource::class;
}
