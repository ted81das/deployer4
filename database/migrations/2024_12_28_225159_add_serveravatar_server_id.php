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
        Schema::table('deployed_servers', function (Blueprint $table) {
            //
 $table->integer('serveravatar_server_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deployed_servers', function (Blueprint $table) {
            //
 $table->dropColumn('serveravatar_server_id');
        });
    }
};
