<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Establecimientos;

use App\Filament\Admin\Resources\Establecimientos\Pages\CreateEstablecimiento;
use App\Filament\Admin\Resources\Establecimientos\Pages\EditEstablecimiento;
use App\Filament\Admin\Resources\Establecimientos\Pages\ListEstablecimientos;
use App\Models\Establecimiento;
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

final class EstablecimientoResource extends Resource
{
    protected static ?string $model = Establecimiento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $modelLabel = 'establecimiento';

    protected static ?string $pluralModelLabel = 'establecimientos';

    protected static ?string $recordTitleAttribute = 'denominacion';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Establecimiento SUNAT')->schema([
                Select::make('empresa_id')
                    ->label('Empresa')
                    ->relationship('empresa', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),
                TextInput::make('codigo')
                    ->label('Código de local')
                    ->default('0000')
                    ->required()
                    ->length(4)
                    ->regex('/^[A-Za-z0-9]{4}$/')
                    ->disabledOn('edit'),
                TextInput::make('denominacion')->label('Denominación')->required()->maxLength(255),
                Toggle::make('es_principal')->label('Domicilio principal')->default(false),
                TextInput::make('ubigeo')->label('Ubigeo')->numeric()->length(6),
                TextInput::make('departamento')->maxLength(255),
                TextInput::make('provincia')->maxLength(255),
                TextInput::make('distrito')->maxLength(255),
                TextInput::make('direccion')->label('Dirección')->maxLength(255)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('empresa.razon_social')->label('Empresa')->searchable()->sortable(),
                TextColumn::make('codigo')->label('Código')->searchable(),
                TextColumn::make('denominacion')->searchable(),
                TextColumn::make('ubigeo')->label('Ubigeo'),
                TextColumn::make('distrito')->label('Distrito'),
                IconColumn::make('es_principal')->label('Principal')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEstablecimientos::route('/'),
            'create' => CreateEstablecimiento::route('/create'),
            'edit' => EditEstablecimiento::route('/{record}/edit'),
        ];
    }
}
