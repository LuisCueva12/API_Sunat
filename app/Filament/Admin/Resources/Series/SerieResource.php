<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Series;

use App\Filament\Admin\Resources\Series\Pages\CreateSerie;
use App\Filament\Admin\Resources\Series\Pages\EditSerie;
use App\Filament\Admin\Resources\Series\Pages\ListSeries;
use App\Models\Serie;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNumberedList;

    protected static ?string $modelLabel = 'serie';

    protected static ?string $pluralModelLabel = 'series';

    protected static ?string $recordTitleAttribute = 'serie';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Numeración')->schema([
                Select::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('tipo_comprobante')
                    ->label('Tipo de comprobante')
                    ->options([
                        'FACTURA' => 'Factura',
                        'BOLETA' => 'Boleta',
                        'NOTA_CREDITO' => 'Nota de crédito',
                        'NOTA_DEBITO' => 'Nota de débito',
                    ])
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('serie')
                    ->required()
                    ->length(4)
                    ->regex('/^[A-Za-z0-9]{4}$/')
                    ->formatStateUsing(fn (?string $state): ?string => $state === null ? null : mb_strtoupper($state))
                    ->disabledOn('edit'),
                TextInput::make('correlativo_actual')
                    ->label('Último correlativo')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit'),
                Toggle::make('activa')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empresa.razon_social')->label('Empresa')->searchable()->sortable(),
                TextColumn::make('tipo_comprobante')->label('Tipo')->badge()->sortable(),
                TextColumn::make('serie')->searchable()->sortable(),
                TextColumn::make('correlativo_actual')->label('Último correlativo')->numeric()->sortable(),
                IconColumn::make('activa')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeries::route('/'),
            'create' => CreateSerie::route('/create'),
            'edit' => EditSerie::route('/{record}/edit'),
        ];
    }
}
