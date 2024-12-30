<?php

namespace App\Services;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use Illuminate\Support\Facades\Storage;
use App\Models\ManagedServerAppWow;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;  // Add this import for Http facade
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;  // Add this if you're using Guzzle directly
use GuzzleHttp\Exception\GuzzleException;

class SSHConnectionService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

public function generateKeyPair(string $username): array
    {
        $privateKey = RSA::createKey();
        $publicKey = $privateKey->getPublicKey();

        return [
            'privateKey' => $privateKey->toString('OpenSSH'),
            'publicKey' => $publicKey->toString('OpenSSH')
        ];
    }



  /**
     * Execute a command on the remote server using application-specific SSH key
     */
    public function executeCommand(ManagedServerAppWow $app, string $command): string
    {
        try {
            // Validate required data
            if (empty($app->application_sshkey_private) || 
                empty($app->application_user) || 
                empty($app->app_hostname)) {
                throw new \Exception('Missing required SSH connection data');
            }

            // Get decrypted private key from database
            $privateKeyString = Crypt::decryptString($app->application_sshkey_private);
            
            // Load the private key
            $privateKey = PublicKeyLoader::load($privateKeyString);

            // Create SSH connection
            $ssh = new SSH2($app->app_hostname);

            // Attempt to login with the application user and private key
            if (!$ssh->login($app->application_user, $privateKey)) {
                throw new \Exception('SSH authentication failed');
            }

            // Build the full command with proper directory change
            $appPath = "/home/{$app->application_user}/{$app->application_name}/public_html";
            $fullCommand = "cd {$appPath} && {$command}";

            // Execute the command
            $output = $ssh->exec($fullCommand);
            
            // Check command exit status
            if ($ssh->getExitStatus() !== 0) {
                throw new \Exception("Command execution failed with output: " . $output);
            }

            // Log successful execution
            Log::info('WP-CLI command executed successfully', [
                'app_id' => $app->id,
                'command' => $command,
                'user' => $app->application_user
            ]);

            return $output;

        } catch (\Exception $e) {
            Log::error('Command execution failed: ' . $e->getMessage(), [
                'app_id' => $app->id,
                'command' => $command,
                'user' => $app->application_user ?? 'unknown'
            ]);
            throw $e;
        } finally {
            if (isset($ssh)) {
                $ssh->disconnect();
            }
        }
    }


 /**
     * Test SSH connection using provided credentials
     * 
     * @param string $hostname Server hostname
     * @param string $username Application username
     * @param string $privateKey Decrypted private key string
     * @return bool Connection status
     */
    public function testConnection(string $hostname, string $username, string $privateKey): bool
    {
        try {
            // Initialize SSH connection
            $ssh = new SSH2($hostname);
            
           // $privateKey = stripcslashes($privateKey);
            $privateKey = str_replace('\n', "\n", $privateKey);

            // Load private key from string
            $key = PublicKeyLoader::load($privateKey);

            // Attempt login
            if (!$ssh->login($username, $key)) {
                Log::error("SSH login failed for user {$username} on {$hostname}");
              //  dd($privateKey,$key,'HERE FAILED');
                return false;
            }

            // Verify application directory access
            $appPath = "/home/{$username}/";
            $result = $ssh->exec("test -d {$appPath} && echo 'exists'");
            //       dd($privateKey,$key,'HERE Success');
            $hasAccess = trim($result) === 'exists';
            
            if (!$hasAccess) {
                Log::error("Directory access failed for {$appPath}");
            }

            return $hasAccess;
            
            //return true;

        } catch (\Exception $e) {
            Log::error("SSH connection test failed: " . $e->getMessage());
            return false;
        }

    }


//SERVER CONNECTION METHODS

public function verifyServerConnection(string $hostname, string $username): array
    {
        try {
            $privateKey = Storage::get('keys/id_rsa');
            if (!$privateKey) {
            //  dd($privateKey,'FAIL');
                throw new \Exception('SSH private key not found');
            }
            
            // Initialize SSH connection
            $ssh = new SSH2($hostname);
            $key = PublicKeyLoader::load($privateKey);
//dd($key,'success');

            if (!$ssh->login('root', $key)) {
                return [
                    'status' => 'failed',
                    'message' => 'SSH connection failed',
                    'details' => null
                ];
            }

            // Get server details
            $serverDetails = [
                'operating_system' => trim($ssh->exec('cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2')),
                'cpu' => (int)trim($ssh->exec("nproc")),
                'memory' => (int)trim($ssh->exec("free -m | awk '/Mem:/ {print $2}'")),
                'php_version' => trim($ssh->exec("php -v | grep -Eo 'PHP [0-9\.]+ ' | head -1")),
                'database_type' => $this->detectDatabaseType($ssh),
            ];

            return [
                'status' => 'active',
                'message' => 'Successfully connected to server',
                'details' => $serverDetails
            ];

        } catch (\Exception $e) {
     //     dd($e->getMessage());
            return [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'details' => null
            ];
        }
    }

    private function detectDatabaseType($ssh): string
    {
        $mysqlRunning = $ssh->exec("pgrep mysqld") !== '';
        $mariadbRunning = $ssh->exec("pgrep mariadb") !== '';
        
        if ($mariadbRunning) return 'mariadb';
        if ($mysqlRunning) return 'mysql';
        return 'unknown';
    }



public function getServerDetails(int $serverId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.serveravatar.token'),
                'Content-Type' => 'application/json',
            ])->get(sprintf(
                'https://api.serveravatar.com/organizations/%s/servers/%s',
                config('services.serveravatar.org_id'),
                $serverId
            ));

            if (!$response->successful()) {
                Log::error('Failed to fetch server details from ServerAvatar. Status: ' . $response->status());
                throw new \Exception('Failed to fetch server details from ServerAvatar');
            }

            $data = $response->json();
            
            if (!isset($data['server'])) {
                Log::error('Invalid response format from ServerAvatar API');
                throw new \Exception('Invalid response format from ServerAvatar API');
            }

            return [
                'serveravatar_org_id' => $data['server']['organization_id'] ?? null,
                'server_ip' => $data['server']['ip'] ?? null,
                'server_name' => $data['server']['name'] ?? null,
                'hostname' => $data['server']['hostname'] ?? null,
                'operating_system' => ($data['server']['operating_system'] ?? '') . ' ' . ($data['server']['version'] ?? ''),
                'version' => $data['server']['version'] ?? null,
                'arch' => $data['server']['arch'] ?? null,
                'cpu' => $data['server']['cores'] ?? null,
                'web_server' => $data['server']['web_server'] ?? null,
                'ssh_status' => $data['server']['ssh_status'] ?? null,
                'php_version' => $data['server']['php_cli_version'] ?? null,
                'database_type' => $data['server']['database_type'] ?? null,
                'redis_password' => $data['server']['redis_password'] ?? null,
                'ssh_port' => $data['server']['ssh_port'] ?? null,
                'phpmyadmin_slug' => $data['server']['phpmyadmin_slug'] ?? null,
                'filemanager_slug' => $data['server']['filemanager_slug'] ?? null,
                'agent_status' => $data['server']['agent_status'] ?? null,
                'agent_version' => $data['server']['agent_version'] ?? null,
                'available_php_versions' => $data['server']['php_versions'] ?? [],
                'timezone' => $data['server']['timezone'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('ServerAvatar API call failed: ' . $e->getMessage());
            throw $e;
        }
    }



}
