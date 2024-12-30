<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deployed_servers', function (Blueprint $table) {
            $table->id();


$table->uuid('uuid')->unique(); // Unique UUID field
            
            // Mandatory fields
            $table->string('server_ip');
            $table->string('owner_email');
            
            // Server identification fields
            $table->string('server_name')->nullable();
            $table->string('hostname')->nullable();
            $table->string('server_ipv6')->nullable();
            
            // Owner and access fields
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('owned_by')->nullable();
            $table->string('root_password')->nullable();
            $table->boolean('is_shared')->default(false);
            
            // Server configuration fields
            $table->string('operating_system')->nullable();
            $table->string('version')->nullable(); // OS version
            $table->string('arch')->nullable(); // Architecture (x86_64, etc)
            $table->integer('cpu')->nullable(); // Number of cores
            $table->integer('memory')->nullable(); // RAM in MB
            
            // ServerAvatar specific fields
            $table->unsignedBigInteger('seravatar_server_id')->nullable();
            $table->unsignedBigInteger('serveravatar_org_id')->nullable();
            $table->string('provider_name')->nullable();
            
            // Server status fields
            $table->enum('server_status', [
                'pending',
                'active',
                'inactive',
                'failed'
            ])->default('pending');
            $table->boolean('ssh_status')->default(false);
            $table->integer('ssh_port')->default(22);
            
            // Software configuration fields
            $table->string('web_server')->nullable(); // nginx, apache, etc
            $table->string('php_version')->nullable();
            $table->json('available_php_versions')->nullable();
            $table->string('database_type')->nullable();
            $table->string('redis_password')->nullable();
            
            // Additional configuration
            $table->string('timezone')->nullable();
            $table->integer('expires_in_days')->nullable();
            $table->string('agent_version')->nullable();
            $table->boolean('agent_status')->default(false);
            
            // Optional features
            $table->string('phpmyadmin_slug')->nullable();
            $table->string('filemanager_slug')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('server_ip');
            $table->index('owner_email');
            $table->index('server_status');
            $table->index('ssh_status');
            $table->index(['seravatar_server_id', 'serveravatar_org_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deployed_servers');

    }
};
