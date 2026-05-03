<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_checks', function (Blueprint $table) {
            if (!Schema::hasColumn('server_checks', 'website_online')) {
                $table->boolean('website_online')->default(false)->after('ssh_online');
            }

            if (!Schema::hasColumn('server_checks', 'cpanel_online')) {
                $table->boolean('cpanel_online')->default(false)->after('website_online');
            }

            if (!Schema::hasColumn('server_checks', 'plesk_online')) {
                $table->boolean('plesk_online')->default(false)->after('cpanel_online');
            }

            if (!Schema::hasColumn('server_checks', 'response_time')) {
                $table->decimal('response_time', 10, 2)->nullable()->after('status');
            }

            if (!Schema::hasColumn('server_checks', 'firewall_status')) {
                $table->string('firewall_status')->nullable()->after('services');
            }
        });
    }

    public function down(): void
    {
        Schema::table('server_checks', function (Blueprint $table) {
            foreach ([
                'website_online',
                'cpanel_online',
                'plesk_online',
                'response_time',
                'firewall_status',
            ] as $column) {
                if (Schema::hasColumn('server_checks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};