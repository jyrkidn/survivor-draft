<?php

namespace App\Filament\Resources\Managers;

use App\Filament\Resources\Managers\Pages\CreateManager;
use App\Filament\Resources\Managers\Pages\EditManager;
use App\Filament\Resources\Managers\Pages\ListManagers;
use App\Filament\Resources\Managers\Schemas\ManagerForm;
use App\Filament\Resources\Managers\Tables\ManagersTable;
use App\Models\Manager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ManagerResource extends Resource
{
    protected static ?string $model = Manager::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'League';

    protected static ?string $navigationLabel = 'Managers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return ManagerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ManagersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListManagers::route('/'),
            'create' => CreateManager::route('/create'),
            'edit' => EditManager::route('/{record}/edit'),
        ];
    }
}
