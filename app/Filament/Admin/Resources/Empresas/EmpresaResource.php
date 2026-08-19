<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Empresas;

use App\Filament\Admin\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Admin\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Admin\Resources\Empresas\Pages\ListEmpresas;
use App\Models\Empresa;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static ?string $recordTitleAttribute = 'razon_social';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos tributarios')->schema([
                TextInput::make('ruc')
                    ->label('RUC')
                    ->required()
                    ->numeric()
                    ->length(11)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('razon_social')
                    ->label('Razón social')
                    ->required()
                    ->maxLength(255),
                TextInput::make('nombre_comercial')
                    ->label('Nombre comercial')
                    ->maxLength(255),
                Select::make('estado')
                    ->options([
                        'ACTIVA' => 'Activa',
                        'INACTIVA' => 'Inactiva',
                        'SUSPENDIDA' => 'Suspendida',
                    ])
                    ->default('ACTIVA')
                    ->required()
                    ->hiddenOn('create'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ruc')->label('RUC')->searchable()->sortable(),
                TextColumn::make('razon_social')->label('Razón social')->searchable()->sortable(),
                TextColumn::make('nombre_comercial')->label('Nombre comercial')->toggleable(),
                TextColumn::make('estado')->badge()->sortable(),
                TextColumn::make('created_at')->label('Registrada')->dateTime()->sortable()->toggleable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmpresas::route('/'),
            'create' => CreateEmpresa::route('/create'),
            'edit' => EditEmpresa::route('/{record}/edit'),
        ];
    }
}
