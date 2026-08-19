<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Series\Pages;

use App\Filament\Admin\Resources\Series\SerieResource;
use Filament\Resources\Pages\EditRecord;

final class EditSerie extends EditRecord
{
    protected static string $resource = SerieResource::class;
}
