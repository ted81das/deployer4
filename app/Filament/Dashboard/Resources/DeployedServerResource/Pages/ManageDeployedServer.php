<?php

namespace App\Filament\Dashboard\Resources\DeployedServerResource\Pages;

use App\Filament\Dashboard\Resources\DeployedServerResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components;
use Filament\Forms\Form;
use App\Services\SSHConnectionService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;  // Add this import
use App\Models\DeployedServer;

class ManageDeployedServer extends Page
{
    protected static string $resource = DeployedServerResource::class;

    protected static string $view = 'filament.dashboard.resources.deployed-server-resource.pages.manage-deployed-server';

      public ?array $data = [];
    
    public ?DeployedServer $record = null;

    public function mount($record): void 
    {
        // Ensure we have a proper model instance
        if (is_string($record) || is_numeric($record)) {
            $record = DeployedServer::find($record);
        }

        if (!$record instanceof Model) {
            abort(404);
        }

        $this->record = $record;
        $this->data = $record->toArray();
    }

    // Add authorization if needed
    public function getHeading(): string
    {
        return "Manage Server: {$this->record->hostname}";
    }

    // Changed from protected to public
    public function getSubheading(): ?string
    {
        return "IP: {$this->record->server_ip}";
    }

    // Optional: Add breadcrumbs
    public function getBreadcrumbs(): array
    {
        return [
            url()->current() => 'Manage Server',
        ];
    }

    // Optional: Add authorization check
    protected function authorizeAccess(): void
    {
        static::authorizeResourceAccess();

        if (!$this->record->server_status === 'active') {
            abort(403, 'Server must be active to manage');
        }
    }
    public function getHeader(): ?View
    {
        return view('filament.dashboard.resources.deployed-server-resource.pages.header', [
            'title' => "Manage Server: {$this->record->server_name}",
             'record' => $this->record  // Make sure to pass the record
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Components\Card::make()
                    ->schema([
                        Components\TextInput::make('server_ip')
                            ->disabled(),
                        Components\TextInput::make('hostname')
                            ->disabled(),
                        Components\TextInput::make('seravatar_server_id')
                            ->disabled(),
                        Components\TextInput::make('operating_system')
                            ->disabled(),
                        Components\TextInput::make('php_version')
                            ->disabled(),
                        Components\TextInput::make('database_type')
                            ->disabled(),
                        Components\TextInput::make('web_server')
                            ->disabled(),
                        Components\TextInput::make('cpu')
                            ->disabled()
                            ->label('CPU Cores'),
                        Components\TextInput::make('memory')
                            ->disabled()
                            ->label('Memory (MB)'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    protected function getActions(): array
    {
        return [
            Actions\Action::make('refresh_connection')
                ->label('Refresh Connection')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $sshService = new SSHConnectionService();
                    $result = $sshService->verifyServerConnection(
                        $this->record->server_ip,
                        $this->record->owner_email
                    );

                    if ($result['status'] === 'active') {
                        $serverDetails = $sshService->getServerDetails($this->record->seravatar_server_id);
                        $this->record->updateFromServerAvatar($serverDetails);
                        
                        Notification::make()
                            ->title('Server Details Updated')
                            ->success()
                            ->send();

                        $this->data = $this->record->fresh()->toArray();
                    } else {
                        $this->record->update(['server_status' => 'failed']);
                        
                        Notification::make()
                            ->title('Connection Failed')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
