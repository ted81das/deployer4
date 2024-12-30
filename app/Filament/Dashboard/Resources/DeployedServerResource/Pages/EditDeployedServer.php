<?php

namespace App\Filament\Dashboard\Resources\DeployedServerResource\Pages;

use App\Filament\Dashboard\Resources\DeployedServerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDeployedServer extends EditRecord
{
    protected static string $resource = DeployedServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
