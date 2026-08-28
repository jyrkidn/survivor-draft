<?php

namespace App\Filament\Resources\Castaways;

use App\Filament\Resources\Castaways\Pages\CreateCastaway;
use App\Filament\Resources\Castaways\Pages\EditCastaway;
use App\Filament\Resources\Castaways\Pages\ListCastaways;
use App\Filament\Resources\Castaways\Schemas\CastawayForm;
use App\Filament\Resources\Castaways\Tables\CastawaysTable;
use App\Models\Castaway;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CastawayResource extends Resource
{
    protected static ?string $model = Castaway::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'League';

    protected static ?string $navigationLabel = 'Castaways';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CastawayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CastawaysTable::configure($table);
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
            'index' => ListCastaways::route('/'),
            'create' => CreateCastaway::route('/create'),
            'edit' => EditCastaway::route('/{record}/edit'),
        ];
    }
}
