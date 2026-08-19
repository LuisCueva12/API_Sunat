<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Comprobante;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteNoEncontradoException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;

final class ComprobanteAcciones
{
    public static function reintentar(): Action
    {
        return Action::make('reintentar')
            ->label('Reintentar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Se volverá a encolar el envío a SUNAT para este comprobante.')
            ->visible(fn (Comprobante $record): bool => $record->estado === 'ERROR')
            ->action(function (Comprobante $record): void {
                try {
                    app(ReintentarComprobante::class)->ejecutar($record->empresa_id, $record->id);

                    Notification::make()
                        ->title('Reintento encolado correctamente')
                        ->success()
                        ->send();
                } catch (ComprobanteNoEncontradoException|TransicionEstadoInvalidaException $e) {
                    Notification::make()
                        ->title('No se pudo reintentar')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
