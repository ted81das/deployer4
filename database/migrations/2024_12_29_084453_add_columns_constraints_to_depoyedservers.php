<?php
// database/migrations/xxxx_xx_xx_update_deployed_servers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('deployed_servers', function (Blueprint $table) {
            // Add new columns
            $table->enum('server_control_panel', ['SA', 'SUW', 'RC', 'GP', 'CW', 'SP'])
                ->default('SA')
                ->after('server_ip')
                ->comment('SA=Serveravatar, SUW=SpinupWP, RC=RunCloud, GP=GridPane, CW=Cloudways, SP=ServerPilot');
                
            $table->enum('server_region_mapping', [
                'NA-East', 
                'NA-West', 
                'NA-Central', 
                'EU-1', 
                'EU-2', 
                'APAC-1', 
                'APAC-2'
            ])->after('server_control_panel');
            
            $table->enum('attached_plan', ['Starter', 'Advanced', 'Premium'])
                ->after('server_region_mapping');

            $table->boolean('is_default')->default(false)->after('attached_plan');

            // Add unique constraints
            $table->unique('server_ip');
            $table->unique('serveravatar_server_id');
            
            // Composite unique constraint for plan, region, and email
            $table->unique(
                ['attached_plan', 'server_region_mapping', 'owner_email'], 
                'unique_server_combination'
            );
        });
    }

    public function down()
    {
        Schema::table('deployed_servers', function (Blueprint $table) {
            // Remove unique constraints
            $table->dropUnique('unique_server_combination');
            $table->dropUnique(['server_ip']);
            $table->dropUnique(['serveravatar_server_id']);

            // Remove columns
            $table->dropColumn([
                'server_control_panel',
                'server_region_mapping',
                'attached_plan',
                'is_default'
            ]);
        });
    }
};
