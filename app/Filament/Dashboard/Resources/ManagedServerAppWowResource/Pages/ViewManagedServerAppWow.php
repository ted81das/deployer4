<?php

namespace App\Filament\Dashboard\Resources\ManagedServerAppWowResource\Pages;

use App\Filament\Dashboard\Resources\ManagedServerAppWowResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewManagedServerAppWow extends ViewRecord
{
    protected static string $resource = ManagedServerAppWowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
