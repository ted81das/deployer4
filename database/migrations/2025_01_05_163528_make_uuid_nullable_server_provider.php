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
        Schema::table('server_providers', function (Blueprint $table) {
            //
   $table->uuid('uuid')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_providers', function (Blueprint $table) {
            //
 $table->uuid('uuid')->nullable(false)->change();
        });
    }
};
