<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('developer_users')) {
            Schema::create('developer_users', function (Blueprint $table) {
                $table->id();

                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');

                $table->string('role')->default('developer');
                $table->string('ssh_username')->nullable();
                $table->string('allowed_project_path')->nullable();

                $table->boolean('can_git_pull')->default(false);
                $table->boolean('can_clear_cache')->default(true);
                $table->boolean('can_composer')->default(false);
                $table->boolean('can_npm')->default(false);
                $table->boolean('can_view_files')->default(true);
                $table->boolean('can_edit_files')->default(false);
                $table->boolean('can_delete_files')->default(false);

                $table->boolean('is_active')->default(true);
                $table->timestamp('last_login_at')->nullable();

                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('developer_users');
    }
};