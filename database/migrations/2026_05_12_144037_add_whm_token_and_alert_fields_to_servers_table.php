<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            /*
            |--------------------------------------------------------------------------
            | WHM / cPanel API Access
            |--------------------------------------------------------------------------
            */
            if (!Schema::hasColumn('servers', 'whm_username')) {
                $table->string('whm_username')->nullable()->after('username');
            }

            if (!Schema::hasColumn('servers', 'whm_token')) {
                $table->text('whm_token')->nullable()->after('whm_username');
            }

            if (!Schema::hasColumn('servers', 'whm_password')) {
                $table->text('whm_password')->nullable()->after('whm_token');
            }

            if (!Schema::hasColumn('servers', 'whm_auth_type')) {
                $table->string('whm_auth_type')->default('token')->after('whm_password');
            }

            if (!Schema::hasColumn('servers', 'whm_port')) {
                $table->unsignedInteger('whm_port')->default(2087)->after('whm_auth_type');
            }

            if (!Schema::hasColumn('servers', 'whm_ssl_verify')) {
                $table->boolean('whm_ssl_verify')->default(false)->after('whm_port');
            }

            /*
            |--------------------------------------------------------------------------
            | WHM Token Status
            |--------------------------------------------------------------------------
            */
            if (!Schema::hasColumn('servers', 'whm_token_last_checked_at')) {
                $table->timestamp('whm_token_last_checked_at')->nullable()->after('whm_ssl_verify');
            }

            if (!Schema::hasColumn('servers', 'whm_token_status')) {
                $table->string('whm_token_status')->nullable()->after('whm_token_last_checked_at');
            }

            if (!Schema::hasColumn('servers', 'whm_token_error')) {
                $table->text('whm_token_error')->nullable()->after('whm_token_status');
            }

            /*
            |--------------------------------------------------------------------------
            | Monitoring Alert Contacts
            |--------------------------------------------------------------------------
            */
            if (!Schema::hasColumn('servers', 'admin_phone')) {
                $table->string('admin_phone', 50)->nullable()->after('whm_token_error');
            }

            if (!Schema::hasColumn('servers', 'admin_email')) {
                $table->string('admin_email')->nullable()->after('admin_phone');
            }

            if (!Schema::hasColumn('servers', 'customer_phone')) {
                $table->string('customer_phone', 50)->nullable()->after('admin_email');
            }

            if (!Schema::hasColumn('servers', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_phone');
            }

            if (!Schema::hasColumn('servers', 'alert_phones')) {
                $table->text('alert_phones')->nullable()->after('customer_email');
            }

            if (!Schema::hasColumn('servers', 'alert_emails')) {
                $table->text('alert_emails')->nullable()->after('alert_phones');
            }

            /*
            |--------------------------------------------------------------------------
            | Monitoring Options
            |--------------------------------------------------------------------------
            */
            if (!Schema::hasColumn('servers', 'monitor_website')) {
                $table->boolean('monitor_website')->default(true)->after('alert_emails');
            }

            if (!Schema::hasColumn('servers', 'monitor_cpanel')) {
                $table->boolean('monitor_cpanel')->default(true)->after('monitor_website');
            }

            if (!Schema::hasColumn('servers', 'monitor_frameworks')) {
                $table->boolean('monitor_frameworks')->default(true)->after('monitor_cpanel');
            }

            if (!Schema::hasColumn('servers', 'send_recovery_alert')) {
                $table->boolean('send_recovery_alert')->default(true)->after('monitor_frameworks');
            }

            /*
            |--------------------------------------------------------------------------
            | Server Check Status
            |--------------------------------------------------------------------------
            */
            if (!Schema::hasColumn('servers', 'last_status')) {
                $table->string('last_status')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('servers', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_status');
            }

            if (!Schema::hasColumn('servers', 'last_checked_at')) {
                $table->timestamp('last_checked_at')->nullable()->after('last_error');
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Backfill WHM Username
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn('servers', 'username') && Schema::hasColumn('servers', 'whm_username')) {
            DB::table('servers')
                ->whereNull('whm_username')
                ->whereNotNull('username')
                ->update([
                    'whm_username' => DB::raw('username'),
                ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Backfill WHM Password
        |--------------------------------------------------------------------------
        */
        if (Schema::hasColumn('servers', 'password') && Schema::hasColumn('servers', 'whm_password')) {
            DB::table('servers')
                ->whereNull('whm_password')
                ->whereNotNull('password')
                ->update([
                    'whm_password' => DB::raw('password'),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $columns = [
                'last_checked_at',
                'last_error',
                'last_status',

                'send_recovery_alert',
                'monitor_frameworks',
                'monitor_cpanel',
                'monitor_website',

                'alert_emails',
                'alert_phones',
                'customer_email',
                'customer_phone',
                'admin_email',
                'admin_phone',

                'whm_token_error',
                'whm_token_status',
                'whm_token_last_checked_at',
                'whm_ssl_verify',
                'whm_port',
                'whm_auth_type',
                'whm_password',
                'whm_token',
                'whm_username',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('servers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};