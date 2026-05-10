<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (!Schema::hasColumn('developer_users', 'vscode_url')) {
                $table->string('vscode_url')->nullable()->after('code_editor_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('developer_users', function (Blueprint $table) {
            if (Schema::hasColumn('developer_users', 'vscode_url')) {
                $table->dropColumn('vscode_url');
            }
        });
    }
};