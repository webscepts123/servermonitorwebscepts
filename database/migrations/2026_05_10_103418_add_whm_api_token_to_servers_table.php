<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'whm_api_token')) {
                $table->text('whm_api_token')->nullable()->after('password');
            }

            if (!Schema::hasColumn('servers', 'whm_username')) {
                $table->string('whm_username')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'whm_api_token')) {
                $table->dropColumn('whm_api_token');
            }

            if (Schema::hasColumn('servers', 'whm_username')) {
                $table->dropColumn('whm_username');
            }
        });
    }
};