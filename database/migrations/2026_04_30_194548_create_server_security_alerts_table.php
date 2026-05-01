<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_security_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('type')->nullable(); // abuse, ssh, mail, firewall, disk, service
            $table->string('level')->default('info'); // info, warning, danger
            $table->string('title');
            $table->longText('message')->nullable();
            $table->string('source_ip')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_security_alerts');
    }
};