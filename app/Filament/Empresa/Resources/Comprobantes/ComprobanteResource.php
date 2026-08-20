<?php

declare(strict_types=1);

namespace App\Filament\Empresa\Resources\Comprobantes;

use App\Filament\Empresa\Resources\Comprobantes\Pages\CreateComprobante;
use App\Filament\Empresa\Resources\Comprobantes\Pages\ListComprobantes;
use App\Filament\Empresa\Resources\Comprobantes\Pages\ViewComprobante;
use App\Filament\Support\ComprobanteAcciones;
use App\Filament\Support\ComprobanteFormato;
use App\Models\Comprobante;
use App\Models\Usuario;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class ComprobanteResource extends Resource
{
    protected static ?string $model = Comprobante::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $modelLabel = 'comprobante';

    protected static ?string $pluralModelLabel = 'comprobantes';

    protected static ?string $recordTitleAttribute = 'serie';

    /**
     * Defensa en profundidad: todas las consultas del recurso quedan
     * restringidas a la empresa del usuario autenticado.
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Usuario $usuario */
        $usuario = Filament::auth()->user();

        return parent::getEloquentQuery()->where('empresa_id', $usuario->empresa_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tipo')->label('Tipo')->badge()->formatStateUsing(fn (string $state): string => ComprobanteFormato::etiquetaTipo($state))->sortable(),
                TextColumn::make('serie')->sortable()->searchable(),
                TextColumn::make('correlativo')->sortable()->searchable(),
                TextColumn::make('receptor_razon_social')->label('Receptor')->searchable()->toggleable(),
                TextColumn::make('total')->money('PEN')->sortable(),
                TextColumn::make('estado')->badge()->color(fn (string $state): string => ComprobanteFormato::colorEstado($state))->formatStateUsing(fn (string $state): string => ComprobanteFormato::etiquetaEstado($state))->sortable(),
                TextColumn::make('fecha_emision')->date()->sortable(),
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
            ])
            ->recordActions([
                ViewAction::make(),
                ComprobanteAcciones::reintentar(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return EmisionComprobanteForm::configurar($schema);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComprobantes::route('/'),
            'create' => CreateComprobante::route('/create'),
            'view' => ViewComprobante::route('/{record}'),
        ];
    }
}
