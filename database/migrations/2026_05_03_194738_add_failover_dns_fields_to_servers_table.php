<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'failover_enabled')) {
                $table->boolean('failover_enabled')->default(false)->after('auto_transfer');
            }

            if (!Schema::hasColumn('servers', 'dns_failover_enabled')) {
                $table->boolean('dns_failover_enabled')->default(false)->after('failover_enabled');
            }

            if (!Schema::hasColumn('servers', 'last_failover_at')) {
                $table->timestamp('last_failover_at')->nullable()->after('dns_failover_enabled');
            }

            if (!Schema::hasColumn('servers', 'last_failover_reason')) {
                $table->text('last_failover_reason')->nullable()->after('last_failover_at');
            }

            if (!Schema::hasColumn('servers', 'original_ip')) {
                $table->string('original_ip')->nullable()->after('linked_domain');
            }

            if (!Schema::hasColumn('servers', 'active_dns_ip')) {
                $table->string('active_dns_ip')->nullable()->after('original_ip');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            foreach ([
                'failover_enabled',
                'dns_failover_enabled',
                'last_failover_at',
                'last_failover_reason',
                'original_ip',
                'active_dns_ip',
            ] as $column) {
                if (Schema::hasColumn('servers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};