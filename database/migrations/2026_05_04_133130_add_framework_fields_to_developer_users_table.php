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
                if (!Schema::hasColumn('developer_users', 'project_type')) {
                    $table->string('project_type')->nullable()->after('allowed_project_path');
                }

                if (!Schema::hasColumn('developer_users', 'framework')) {
                    $table->string('framework')->nullable()->after('project_type');
                }

                if (!Schema::hasColumn('developer_users', 'project_root')) {
                    $table->string('project_root')->nullable()->after('framework');
                }

                if (!Schema::hasColumn('developer_users', 'build_command')) {
                    $table->string('build_command')->nullable()->after('project_root');
                }

                if (!Schema::hasColumn('developer_users', 'deploy_command')) {
                    $table->string('deploy_command')->nullable()->after('build_command');
                }

                if (!Schema::hasColumn('developer_users', 'start_command')) {
                    $table->string('start_command')->nullable()->after('deploy_command');
                }

                if (!Schema::hasColumn('developer_users', 'can_run_build')) {
                    $table->boolean('can_run_build')->default(false)->after('can_npm');
                }

                if (!Schema::hasColumn('developer_users', 'can_run_python')) {
                    $table->boolean('can_run_python')->default(false)->after('can_run_build');
                }

                if (!Schema::hasColumn('developer_users', 'can_restart_app')) {
                    $table->boolean('can_restart_app')->default(false)->after('can_run_python');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('developer_users')) {
            Schema::table('developer_users', function (Blueprint $table) {
                foreach ([
                    'project_type',
                    'framework',
                    'project_root',
                    'build_command',
                    'deploy_command',
                    'start_command',
                    'can_run_build',
                    'can_run_python',
                    'can_restart_app',
                ] as $column) {
                    if (Schema::hasColumn('developer_users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};