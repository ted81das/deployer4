<?php

namespace App\Filament\Dashboard\Resources\DeployedServerResource\Pages;

use App\Filament\Dashboard\Resources\DeployedServerResource;
//use Filament\Dashboard\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord;  // Correct namespace for CreateRecord
use App\Services\SSHConnectionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;  // Added Storage facade import
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;

class CreateDeployedServer extends CreateRecord
{
    protected static string $resource = DeployedServerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.serveravatar.token'),
                'Content-Type' => 'application/json',
            ])->get(sprintf(
                'https://api.serveravatar.com/organizations/%s/servers/%s',
                config('services.serveravatar.org_id'),
                $data['serveravatar_server_id']
            ));

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch server details from ServerAvatar');
            }

            $serverData = $response->json()['server'];

            return array_merge($data, [
                'serveravatar_org_id' => $serverData['organization_id'],
                'server_ip' => $serverData['ip'],
                'server_name' => $serverData['name'],
                'hostname' => $serverData['hostname'],
                'operating_system' => $serverData['operating_system'] . ' ' . $serverData['version'],
                'version' => $serverData['version'],
                'arch' => $serverData['arch'],
                'cpu' => $serverData['cores'],
                'web_server' => $serverData['web_server'],
                'ssh_status' => $serverData['ssh_status'],
                'php_version' => $serverData['php_cli_version'],
                'database_type' => $serverData['database_type'],
                'redis_password' => $serverData['redis_password'],
                'ssh_port' => $serverData['ssh_port'],
                'phpmyadmin_slug' => $serverData['phpmyadmin_slug'],
                'filemanager_slug' => $serverData['filemanager_slug'],
                'agent_status' => $serverData['agent_status'],
                'agent_version' => $serverData['agent_version'],
                'available_php_versions' => json_encode($serverData['php_versions']),
                'timezone' => $serverData['timezone'],
                'server_status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('ServerAvatar API Error: ' . $e->getMessage());
            
            Notification::make()
                ->title('API Error')
                ->danger()
                ->body('Failed to fetch server details from ServerAvatar')
                ->send();

            throw $e;
        }
    }

    protected function afterCreate(): void
    {
        $sshService = new SSHConnectionService();
        
        try {
            $privateKey = Storage::get('keys/id_rsa');
            if (!$privateKey) {
                throw new \Exception('SSH private key not found');
            }

            $connectionResult = $sshService->verifyServerConnection(
                $this->record->server_ip,
                $this->record->owner_email
            );

            if ($connectionResult['status'] === 'connected') {
                $this->record->update(['server_status' => 'connected']);
                
                Notification::make()
                    ->title('Server Created Successfully')
                    ->success()
                    ->body('SSH connection verified and server details updated')
                    ->send();
            } else {
                $this->record->update(['server_status' => 'failed']);
                
                Notification::make()
                    ->title('SSH Connection Failed')
                    ->danger()
                    ->body('Server created but SSH connection failed')
                    ->send();
            }
        } catch (\Exception $e) {
            $this->record->update(['server_status' => 'failed']);
            
            Notification::make()
                ->title('SSH Connection Error')
                ->danger()
                ->body($e->getMessage())
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
