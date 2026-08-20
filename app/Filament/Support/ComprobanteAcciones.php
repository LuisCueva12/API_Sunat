<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Comprobante;
use App\Services\Comprobantes\GeneradorRepresentacionImpresa;
use App\Services\Comprobantes\XmlFirmadoNoDisponible;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;
use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteNoEncontradoException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class ComprobanteAcciones
{
    public static function descargarPdf(): Action
    {
        return Action::make('descargar_pdf')
            ->label('Ticket / Imprimir')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('primary')
            ->visible(fn (Comprobante $record): bool => in_array($record->estado, ['ACEPTADO', 'ACEPTADO_CON_OBSERVACIONES'], true))
            ->action(function (Comprobante $record): ?StreamedResponse {
                try {
                    $representacion = app(GeneradorRepresentacionImpresa::class)->generar($record);
                } catch (XmlFirmadoNoDisponible $e) {
                    Log::warning('No se encontró el XML firmado para generar la representación impresa.', [
                        'comprobante_id' => $record->id,
                        'empresa_id' => $record->empresa_id,
                        'mensaje' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('No encontramos el XML firmado')
                        ->body('Este comprobante no conserva el archivo necesario para crear un PDF válido. Solicita ayuda para recuperarlo.')
                        ->danger()
                        ->send();

                    return null;
                } catch (Throwable $e) {
                    Log::error('No se pudo generar la representación impresa.', [
                        'comprobante_id' => $record->id,
                        'empresa_id' => $record->empresa_id,
                        'excepcion' => $e::class,
                        'mensaje' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->title('El PDF todavía no está disponible')
                        ->body('No pudimos preparar la representación impresa. Intenta nuevamente o solicita ayuda.')
                        ->danger()
                        ->send();

                    return null;
                }

                return response()->streamDownload(
                    static function () use ($representacion): void {
                        echo $representacion->contenido;
                    },
                    $representacion->nombreArchivo,
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

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
