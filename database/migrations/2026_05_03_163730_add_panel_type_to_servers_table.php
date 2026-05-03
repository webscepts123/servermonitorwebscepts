<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'panel_type')) {
                $table->string('panel_type', 50)->nullable()->after('host');
            }
        });

        if (Schema::hasColumn('servers', 'panel_type')) {
            DB::table('servers')
                ->whereNull('panel_type')
                ->update(['panel_type' => 'cpanel']);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'panel_type')) {
                $table->dropColumn('panel_type');
            }
        });
    }
};