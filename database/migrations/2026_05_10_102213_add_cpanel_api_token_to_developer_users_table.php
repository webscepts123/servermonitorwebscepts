<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_users', 'cpanel_api_token')) {
                $table->text('cpanel_api_token')->nullable()->after('temporary_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (Schema::hasColumn('developer_users', 'cpanel_api_token')) {
                $table->dropColumn('cpanel_api_token');
            }
        });
    }
};