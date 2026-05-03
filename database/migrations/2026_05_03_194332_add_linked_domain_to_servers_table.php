<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (!Schema::hasColumn('servers', 'linked_domain')) {
                if (Schema::hasColumn('servers', 'website_url')) {
                    $table->string('linked_domain')->nullable()->after('website_url');
                } else {
                    $table->string('linked_domain')->nullable()->after('host');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            if (Schema::hasColumn('servers', 'linked_domain')) {
                $table->dropColumn('linked_domain');
            }
        });
    }
};