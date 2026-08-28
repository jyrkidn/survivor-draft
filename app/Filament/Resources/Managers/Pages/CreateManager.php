<?php

namespace App\Filament\Resources\Managers\Pages;

use App\Filament\Resources\Managers\ManagerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateManager extends CreateRecord
{
    protected static string $resource = ManagerResource::class;
}
