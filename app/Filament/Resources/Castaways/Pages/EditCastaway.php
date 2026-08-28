<?php

namespace App\Filament\Resources\Castaways\Pages;

use App\Filament\Resources\Castaways\CastawayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCastaway extends EditRecord
{
    protected static string $resource = CastawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
