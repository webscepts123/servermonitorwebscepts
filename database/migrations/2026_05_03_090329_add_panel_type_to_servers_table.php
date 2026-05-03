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
        if (!Schema::hasColumn('servers', 'panel_type')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->string('panel_type')->nullable()->after('website_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('servers', 'panel_type')) {
            Schema::table('servers', function (Blueprint $table) {
                $table->dropColumn('panel_type');
            });
        }
    }
};
