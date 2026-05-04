<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('developer_users')) {
            Schema::table('developer_users', function (Blueprint $table) {
                if (!Schema::hasColumn('developer_users', 'cpanel_username')) {
                    $table->string('cpanel_username')->nullable()->unique()->after('email');
                }

                if (!Schema::hasColumn('developer_users', 'contact_email')) {
                    $table->string('contact_email')->nullable()->after('cpanel_username');
                }

                if (!Schema::hasColumn('developer_users', 'cpanel_domain')) {
                    $table->string('cpanel_domain')->nullable()->after('contact_email');
                }

                if (!Schema::hasColumn('developer_users', 'server_id')) {
                    $table->unsignedBigInteger('server_id')->nullable()->after('cpanel_domain');
                }

                if (!Schema::hasColumn('developer_users', 'temporary_password')) {
                    $table->text('temporary_password')->nullable()->after('password');
                }

                if (!Schema::hasColumn('developer_users', 'password_must_change')) {
                    $table->boolean('password_must_change')->default(true)->after('temporary_password');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('developer_users')) {
            Schema::table('developer_users', function (Blueprint $table) {
                foreach ([
                    'cpanel_username',
                    'contact_email',
                    'cpanel_domain',
                    'server_id',
                    'temporary_password',
                    'password_must_change',
                ] as $column) {
                    if (Schema::hasColumn('developer_users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};