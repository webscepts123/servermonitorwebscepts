<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->foreignId('backup_server_id')->nullable()->after('id');
            $table->integer('disk_warning_percent')->default(60);
            $table->integer('disk_transfer_percent')->default(90);
            $table->string('google_drive_remote')->nullable()->default('gdrive');
            $table->string('backup_path')->default('/backup');
            $table->string('local_backup_path')->nullable();
            $table->string('sync_time')->nullable();
            $table->boolean('auto_transfer')->default(false);
            $table->boolean('google_drive_sync')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'backup_server_id',
                'disk_warning_percent',
                'disk_transfer_percent',
                'google_drive_remote',
                'backup_path',
                'local_backup_path',
                'sync_time',
                'auto_transfer',
                'google_drive_sync',
            ]);
        });
    }
};