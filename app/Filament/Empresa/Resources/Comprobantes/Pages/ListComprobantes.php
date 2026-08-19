<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes\Pages;

use App\Filament\Empresa\Resources\Comprobantes\ComprobanteResource;
use Filament\Resources\Pages\ListRecords;

final class ListComprobantes extends ListRecords
{
    protected static string $resource = ComprobanteResource::class;
}
