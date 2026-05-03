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
            if (!Schema::hasColumn('servers', 'status')) {
                $table->string('status', 20)->default('offline')->after('password');
            }
        });

        if (Schema::hasColumn('servers', 'status')) {
            DB::table('servers')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update(['status' => 'offline']);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};