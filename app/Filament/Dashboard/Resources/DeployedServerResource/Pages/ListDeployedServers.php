<?php

namespace App\Filament\Dashboard\Resources\DeployedServerResource\Pages;

use App\Filament\Dashboard\Resources\DeployedServerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDeployedServers extends ListRecords
{
    protected static string $resource = DeployedServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
