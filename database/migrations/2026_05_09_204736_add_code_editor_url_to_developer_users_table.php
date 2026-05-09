<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_users', 'code_editor_url')) {
                $table->string('code_editor_url')->nullable()->after('project_root');
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (Schema::hasColumn('developer_users', 'code_editor_url')) {
                $table->dropColumn('code_editor_url');
            }
        });
    }
};