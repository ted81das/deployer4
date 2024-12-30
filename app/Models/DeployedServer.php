<?php

namespace App\Models;

use App\Traits\HasUUID;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\SSHConnectionService;

class DeployedServer extends Model
{
    use HasFactory, HasUUID;

    protected $fillable = [
        'uuid',
        'server_ip',
        'server_ipv6',
        'server_control_panel',
        'server_region_mapping',
        'attached_plan',
        'is_default',
        'hostname',
        'server_name',
        'owner_user_id',
        'owner_email',
        'root_password',
        'operating_system',
        'server_status',
        'seravatar_server_id',
        'serveravatar_server_id',
        'serveravatar_org_id',
        'memory',
        'cpu',
        'php_version',
        'owned_by',
        'is_shared',
        'expires_in_days',
        'ssh_status',
        'database_type'
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_shared' => 'boolean',
        'memory' => 'integer',
        'cpu' => 'integer',
        'expires_in_days' => 'integer',
        'owner_user_id' => 'integer',
        'seravatar_server_id' => 'integer',
        'serveravatar_org_id' => 'integer',
        'ssh_status' => 'integer',
        'is_default' => 'boolean'
    ];

    protected $attributes = [
        'server_status' => 'pending',
        'is_shared' => false,
        'ssh_status' => 0
    ];
    
    
    // Define constants for control panel options
    const CONTROL_PANEL = [
        'SA' => 'Serveravatar',
        'SUW' => 'SpinupWP',
        'RC' => 'RunCloud',
        'GP' => 'GridPane',
        'CW' => 'Cloudways',
        'SP' => 'ServerPilot'
    ];

    // Define constants for region mapping
    const REGIONS = [
        'NA-East' => 'NA - East',
        'NA-West' => 'NA - West',
        'NA-Central' => 'NA - Central',
        'EU-1' => 'EU 1',
        'EU-2' => 'EU 2',
        'APAC-1' => 'APAC 1',
        'APAC-2' => 'APAC 2'
    ];

    // Define constants for plans
    const PLANS = [
        'Starter' => 'Starter',
        'Advanced' => 'Advanced',
        'Premium' => 'Premium'
    ];



/**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Before saving, ensure only one default server per owner_email
        static::saving(function ($server) {
            if ($server->is_default) {
                static::where('owner_email', $server->owner_email)
                    ->where('id', '!=', $server->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }



 /**
     * Get server IP by region and plan
     *
     * @param string $region
     * @param string $plan
     * @return string|null
     */
    public static function getServerIpByRegionAndPlan(string $region, string $plan): ?string
    {
        return static::where('server_region_mapping', $region)
            ->where('attached_plan', $plan)
            ->where('server_status', 'active')
            ->value('server_ip');
    }

    /**
     * Get default server IP for an owner
     *
     * @param string $ownerEmail
     * @return string|null
     */
    public static function getDefaultServerIp(string $ownerEmail): ?string
    {
        return static::where('owner_email', $ownerEmail)
            ->where('is_default', true)
            ->value('server_ip');
    }


    // Update server details from ServerAvatar API response
    public function updateFromServerAvatar(array $apiData): void
    {
      //  dd($apiData);
        
        $this->update([
          
    'server_ip' => $apiData['server_ip'] ?? $this->server_ip,
    'server_name' => $apiData['server_name'] ?? $this->server_name,
    'hostname' => $apiData['hostname'] ?? $this->hostname,
    'operating_system' => $apiData['operating_system'] ?? $this->operating_system,
    'cpu' => $apiData['cpu'] ?? $this->cpu,
    'php_version' => $apiData['php_version'] ?? $this->php_version,
    'serveravatar_server_id' => $apiData['serveravatar_server_id'] ?? $this->serveravatar_server_id,
    'serveravatar_org_id' => $apiData['serveravatar_org_id'] ?? $this->serveravatar_org_id,
    'ssh_status' => $apiData['ssh_status'] ?? $this->ssh_status,
    'database_type' => $apiData['database_type'] ?? $this->database_type,
    
    /*'web_server' => $apiData['web_server'] ?? $this->web_server,
    'redis_password' => $apiData['redis_password'] ?? $this->redis_password,
    'ssh_port' => $apiData['ssh_port'] ?? $this->ssh_port,
    'phpmyadmin_slug' => $apiData['phpmyadmin_slug'] ?? $this->phpmyadmin_slug,
    'filemanager_slug' => $apiData['filemanager_slug'] ?? $this->filemanager_slug,
    'agent_status' => $apiData['agent_status'] ?? $this->agent_status,
    'agent_version' => $apiData['agent_version'] ?? $this->agent_version,
    'available_php_versions' => $apiData['available_php_versions'] ?? $this->available_php_versions,
    'timezone' => $apiData['timezone'] ?? $this->timezone,
    'version' => $apiData['version'] ?? $this->version,
    'arch' => $apiData['arch'] ?? $this->arch*/
          
 
        ]);
    }

    // Update server details from SSH Connection
    public function updateFromSSH(string $privateKey): void
    {
        try {
            $sshService = new SSHConnectionService();
            
            if ($sshService->testConnection($this->hostname, $this->owner_email, $privateKey)) {
                $this->ssh_status = 1;
                // Additional SSH details fetching logic can be added here
                $this->save();
            }
        } catch (\Exception $e) {
            $this->ssh_status = 0;
            $this->server_status = 'failed';
            $this->save();
            
            throw $e;
        }
    }

    // Scope for active servers
    public function scopeActive($query)
    {
        return $query->where('server_status', 'active');
    }

    // Scope for servers with active SSH
    public function scopeWithActiveSSH($query)
    {
        return $query->where('ssh_status', 1);
    }

    // Check if server is accessible via SSH
    public function isSSHAccessible(): bool
    {
        return $this->ssh_status === 1;
    }

    // Get server full OS name
    public function getFullOSNameAttribute(): string
    {
        return trim($this->operating_system);
    }

  
      /**
     * Get server IP by region and plan with optional owner email filter
     *
     * @param string $region
     * @param string $plan
     * @param string|null $ownerEmail
     * @return string|null
     */
    public static function findServerIp(
        string $region, 
        string $plan, 
        ?string $ownerEmail = null
    ): ?string {
        $query = static::where('server_region_mapping', $region)
            ->where('attached_plan', $plan)
            ->where('server_status', 'active');

        if ($ownerEmail) {
            $query->where('owner_email', $ownerEmail);
        }

        return $query->value('server_ip');
    }

    
    /**
     * Set server as default
     *
     * @return bool
     */
    public function setAsDefault(): bool
    {
        DB::transaction(function () {
            // Remove default status from other servers of the same owner
            static::where('owner_email', $this->owner_email)
                ->where('id', '!=', $this->id)
                ->update(['is_default' => false]);

            // Set this server as default
            $this->is_default = true;
            $this->save();
        });

        return true;
    }
    
    /**
     * Validate server combination
     *
     * @param string $plan
     * @param string $region
     * @param string $email
     * @return bool
     */
    public static function isValidCombination(
        string $plan, 
        string $region, 
        string $email
    ): bool {
        return !static::where('attached_plan', $plan)
            ->where('server_region_mapping', $region)
            ->where('owner_email', $email)
            ->exists();
    }
  
  
  
  
  
  
}
