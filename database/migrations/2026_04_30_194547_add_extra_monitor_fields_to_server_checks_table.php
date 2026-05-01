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
        Schema::table('server_checks', function (Blueprint $table) {
            $table->boolean('cpanel_online')->default(false);
            $table->boolean('plesk_online')->default(false);
            $table->boolean('website_online')->default(false);
            $table->boolean('ssh_online')->default(false);
            $table->text('firewall_status')->nullable();
            $table->text('security_alerts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('server_checks', function (Blueprint $table) {
            //
        });
    }
};
