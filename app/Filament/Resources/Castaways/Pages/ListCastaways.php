<?php

namespace App\Filament\Resources\Castaways\Pages;

use App\Filament\Resources\Castaways\CastawayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCastaways extends ListRecords
{
    protected static string $resource = CastawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
