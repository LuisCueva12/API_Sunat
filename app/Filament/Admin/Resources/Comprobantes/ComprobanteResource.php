<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Comprobantes;

use App\Filament\Admin\Resources\Comprobantes\Pages\ListComprobantes;
use App\Filament\Admin\Resources\Comprobantes\Pages\ViewComprobante;
use App\Models\Comprobante;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Facturacion\Application\CasosDeUso\ReintentarComprobante;
use Modules\Facturacion\Domain\Excepciones\ComprobanteNoEncontradoException;
use Modules\Facturacion\Domain\Excepciones\TransicionEstadoInvalidaException;

final class ComprobanteResource extends Resource
{
    protected static ?string $model = Comprobante::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'comprobante';

    protected static ?string $pluralModelLabel = 'comprobantes';

    protected static ?string $recordTitleAttribute = 'serie';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empresa.razon_social')->label('Empresa')->searchable()->sortable(),
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state): string => self::etiquetaTipo($state))->sortable(),
                TextColumn::make('serie')->sortable()->searchable(),
                TextColumn::make('correlativo')->sortable()->searchable(),
                TextColumn::make('receptor_razon_social')->label('Receptor')->searchable()->toggleable(),
                TextColumn::make('total')->money('PEN')->sortable(),
                TextColumn::make('estado')->badge()->color(fn (string $state): string => self::colorEstado($state))->formatStateUsing(fn (string $state): string => self::etiquetaEstado($state))->sortable(),
                TextColumn::make('intentos_envio')->label('Intentos')->sortable()->toggleable(),
                TextColumn::make('fecha_emision')->date()->sortable(),
                TextColumn::make('created_at')->label('Registrado')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')->options([
                    'REGISTRADO' => 'Registrado',
                    'PROCESANDO' => 'Procesando',
                    'ACEPTADO' => 'Aceptado',
                    'ACEPTADO_CON_OBSERVACIONES' => 'Aceptado con observaciones',
                    'RECHAZADO' => 'Rechazado',
                    'ERROR' => 'Error',
                ]),
                SelectFilter::make('tipo')->options([
                    'FACTURA' => 'Factura',
                    'BOLETA' => 'Boleta',
                    'NOTA_CREDITO' => 'Nota de crédito',
                    'NOTA_DEBITO' => 'Nota de débito',
                ]),
                SelectFilter::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                self::accionReintentar(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function accionReintentar(): Action
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

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'FACTURA' => 'Factura',
            'BOLETA' => 'Boleta',
            'NOTA_CREDITO' => 'Nota de crédito',
            'NOTA_DEBITO' => 'Nota de débito',
            default => $tipo,
        };
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match ($estado) {
            'REGISTRADO' => 'Registrado',
            'PROCESANDO' => 'Procesando',
            'ACEPTADO' => 'Aceptado',
            'ACEPTADO_CON_OBSERVACIONES' => 'Aceptado con observaciones',
            'RECHAZADO' => 'Rechazado',
            'ERROR' => 'Error',
            default => $estado,
        };
    }

    public static function colorEstado(string $estado): string
    {
        return match ($estado) {
            'ACEPTADO' => 'success',
            'ACEPTADO_CON_OBSERVACIONES' => 'warning',
            'PROCESANDO' => 'info',
            'RECHAZADO', 'ERROR' => 'danger',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComprobantes::route('/'),
            'view' => ViewComprobante::route('/{record}'),
        ];
    }
}
