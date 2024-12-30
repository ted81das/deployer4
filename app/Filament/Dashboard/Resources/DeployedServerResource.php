<?php

namespace App\Filament\Dashboard\Resources;

use App\Filament\Dashboard\Resources\DeployedServerResource\Pages;
use App\Models\DeployedServer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Services\SSHConnectionService;  // Add this import
use Filament\Notifications\Notification;  // Add this import
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Illuminate\Validation\Rule;

class DeployedServerResource extends Resource
{
    protected static ?string $model = DeployedServer::class;

    protected static ?string $navigationIcon = 'heroicon-o-server';
    
    // Add this method to define the correct route name
    public static function getSlug(): string
    {
        return 'deployed-servers';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('server_ip')
                    ->label('Server IP')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->ip(),

            Forms\Components\Select::make('server_control_panel')
    ->label('Control Panel')
    ->options([
        'SA' => 'Serveravatar (SA)',
        'SUW' => 'SpinupWP (SUW)',
        'RC' => 'RunCloud (RC)',
        'GP' => 'GridPane (GP)',
        'CW' => 'Cloudways (CW)',
        'SP' => 'ServerPilot (SP)'
    ])
    ->default('SA')
    ->disabled(fn ($record) => $record && $record->server_control_panel !== 'SA')
    ->required(),

                Forms\Components\Select::make('server_region_mapping')
                    ->label('Server Region')
                    ->options(DeployedServer::REGIONS)
                    ->required(),

                Forms\Components\Select::make('attached_plan')
                    ->label('Plan')
                    ->options(DeployedServer::PLANS)
                    ->required(),

                Forms\Components\Toggle::make('is_default')
                    ->label('Set as Default Server')
                    ->default(false)
                    ->rules([
                        function () {
                            return function ($attribute, $value, $fail) {
                                if ($value) {
                                    $exists = DeployedServer::where('owner_email', $this->owner_email)
                                        ->where('is_default', true)
                                        ->where('id', '!=', $this->record?->id)
                                        ->exists();

                                    if ($exists) {
                                        $fail("Another server is already set as default for this owner.");
                                    }
                                }
                            };
                        }
                    ]),

                Forms\Components\TextInput::make('hostname')
                    ->label('Hostname')
                    ->required(),

           Forms\Components\TextInput::make('owner_email')
                    ->label('Owner Email')
                    ->required()
                    ->email(),

           Forms\Components\TextInput::make('serveravatar_server_id')
                    ->label('CP Server ID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->numeric()
                    ->rules(['required', 'integer']),
            ]);
    }


public static function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->schema([
            Section::make('Server Information')
                ->description('Detailed server configuration and status')
                ->icon('heroicon-o-server')
                ->collapsible()
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextEntry::make('server_ip')
                                ->label('Server IP')
                                ->copyable()
                                ->copyMessage('IP copied!')
                                ->copyMessageDuration(1500)
                                ->icon('heroicon-o-globe-alt'),
                                
                            TextEntry::make('server_control_panel')
                                    ->label('Control Panel')
                                    ->formatStateUsing(fn (string $state): string => 
                                        DeployedServer::CONTROL_PANEL[$state] . " ($state)"),

                            TextEntry::make('server_region_mapping')
                                    ->label('Server Region'),

                            TextEntry::make('attached_plan')
                                    ->label('Plan')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'Premium' => 'success',
                                        'Advanced' => 'warning',
                                        'Starter' => 'info',
                                    }),

                            TextEntry::make('server_name')
                                ->label('Server Name')
                                ->weight('bold'),

                            TextEntry::make('hostname')
                                ->label('Hostname')
                                ->copyable(),

                            TextEntry::make('owner_email')
                                ->label('Owner Email')
                                ->copyable()
                                ->icon('heroicon-o-envelope')
                                ->url(fn (string $state): string => "mailto:{$state}")
                                ->openUrlInNewTab(),

                            TextEntry::make('operating_system')
                                ->label('Operating System')
                                ->icon('heroicon-o-computer-desktop'),

                            TextEntry::make('cpu')
                                ->label('CPU Cores')
                                ->suffix(' cores')
                                ->numeric(),

                            TextEntry::make('php_version')
                                ->label('PHP Version')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('ssh_status')
                                ->label('SSH Status')
                                ->badge()
                                ->icon(fn (string $state): string => match ($state) {
                                    '1' => 'heroicon-o-check-circle',
                                    '0' => 'heroicon-o-x-circle',
                                    default => 'heroicon-o-question-mark-circle',
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    '1' => 'success',
                                    '0' => 'danger',
                                    default => 'warning',
                                })
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    '1' => 'Connected',
                                    '0' => 'Disconnected',
                                    default => 'Unknown',
                                }),

                            TextEntry::make('database_type')
                                ->label('Database Type')
                                ->icon('heroicon-o-x-circle'),

                           /* TextEntry::make('timezone')
                                ->label('Timezone')
                                ->icon('heroicon-o-clock')
                                ->helperText(fn (string $state): string => 
                                    "Current time: " . now()->timezone($state)->format('Y-m-d H:i:s')),
                                    */
                        ]),
                ]),
        ]);
}






    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('server_ip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostname')
                    ->searchable(),
                Tables\Columns\TextColumn::make('owner_email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('serveravatar_server_id')
                    ->label('Server DB ID'),
          Tables\Columns\BadgeColumn::make('server_status')
                    ->colors([
                        'danger' => 'failed',
                        'warning' => 'pending',
                        'success' => 'active',
                        'gray' => 'inactive'
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('verify_connection')
                    ->label('Verify Connection')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (DeployedServer $record) {
                        $sshService = new SSHConnectionService();
                        $result = $sshService->verifyServerConnection(
                            $record->server_ip,
                            $record->owner_email
                        );

                        if ($result['status'] === 'active') {
                          //dd($result['status']);
                            $record->update(['server_status' => 'active']);
                            try {
                             
                                $serverDetails = $sshService->getServerDetails($record->serveravatar_server_id);
                               // dd($serverDetails);
                                $record->updateFromServerAvatar($serverDetails);
                               
                               
                                
                                
                                Notification::make()
                                    ->title('Connection Verified')
                                    ->success()
                                    ->send();

                               return;
                               //return redirect()->route('filament.dashboard.resources.deployed-servers.manage', $record);
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('API Sync Failed')
                                    ->danger()
                                    ->send();
                            }
                        }
                        else {$record->update(['server_status' => 'failed']);}
                        

                        
                    }),
                Tables\Actions\Action::make('manage')
                    ->label('Manage Server')
                    ->icon('heroicon-o-cog')
                    ->url(fn (DeployedServer $record) => 
                        route('filament.dashboard.resources.deployed-servers.manage', $record))
                    ->visible(fn (DeployedServer $record) => 
                        $record->server_status === 'active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeployedServers::route('/'),
            'create' => Pages\CreateDeployedServer::route('/create'),
            //'edit' => Pages\EditDeployedServer::route('/{record}/edit'),
             'view' => Pages\ViewDeployedServer::route('/{record}/view'),
           'manage' => Pages\ManageDeployedServer::route('/{record}/manage'),
        ];
    }
}
