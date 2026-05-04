<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servers')) {
            return;
        }

        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'whm_username')) {
                $table->string('whm_username')->nullable()->after('username');
            }

            if (!Schema::hasColumn('servers', 'whm_token')) {
                $table->longText('whm_token')->nullable()->after('whm_username');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('servers')) {
            return;
        }

        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'whm_token')) {
                $table->dropColumn('whm_token');
            }

            if (Schema::hasColumn('servers', 'whm_username')) {
                $table->dropColumn('whm_username');
            }
        });
    }
};