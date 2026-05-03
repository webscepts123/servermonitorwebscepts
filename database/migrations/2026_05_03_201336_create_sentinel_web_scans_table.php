<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sentinel_web_scans')) {
            Schema::create('sentinel_web_scans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('server_id')->nullable()->constrained('servers')->nullOnDelete();

                $table->string('url');
                $table->string('domain')->nullable();
                $table->string('ip')->nullable();

                $table->integer('http_status')->nullable();
                $table->decimal('response_time_ms', 10, 2)->nullable();

                $table->boolean('ssl_valid')->default(false);
                $table->timestamp('ssl_expires_at')->nullable();

                $table->json('detected_technologies')->nullable();
                $table->json('security_headers')->nullable();
                $table->json('missing_headers')->nullable();
                $table->json('exposed_files')->nullable();
                $table->json('database_risks')->nullable();
                $table->json('framework_risks')->nullable();

                $table->integer('risk_score')->default(0);
                $table->string('risk_level')->default('low');

                $table->longText('summary')->nullable();
                $table->timestamps();

                $table->index('domain');
                $table->index('risk_level');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sentinel_web_scans');
    }
};