<?php

namespace App\Filament\Dashboard\Resources\DeployedServerResource\Pages;

use App\Filament\Dashboard\Resources\DeployedServerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDeployedServer extends ViewRecord
{
    protected static string $resource = DeployedServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            // Action with confirmation
Actions\DeleteAction::make()
    ->requiresConfirmation()
    ->modalHeading('Delete Server')
    ->modalDescription('Are you sure?')
    ->modalSubmitActionLabel('Yes, delete')
    ->modalCancelActionLabel('No, cancel')
        ];
    }
}
