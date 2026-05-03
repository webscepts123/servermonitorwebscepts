<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('server_domains')) {
            Schema::create('server_domains', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
                $table->string('domain');
                $table->boolean('is_primary')->default(false);
                $table->string('dns_provider')->default('cloudns');
                $table->string('active_dns_ip')->nullable();
                $table->timestamp('last_dns_update_at')->nullable();
                $table->timestamps();

                $table->unique(['server_id', 'domain']);
                $table->index('domain');
            });
        }

        if (Schema::hasColumn('servers', 'linked_domain')) {
            $servers = DB::table('servers')
                ->whereNotNull('linked_domain')
                ->where('linked_domain', '!=', '')
                ->get();

            foreach ($servers as $server) {
                DB::table('server_domains')->updateOrInsert(
                    [
                        'server_id' => $server->id,
                        'domain' => $server->linked_domain,
                    ],
                    [
                        'is_primary' => true,
                        'dns_provider' => 'cloudns',
                        'active_dns_ip' => $server->active_dns_ip ?? $server->host ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_domains');
    }
};