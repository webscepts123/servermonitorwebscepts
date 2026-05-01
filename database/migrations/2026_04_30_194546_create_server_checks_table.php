<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('server_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->boolean('online')->default(false);
            $table->string('status')->nullable();
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('ram_usage', 5, 2)->nullable();
            $table->decimal('disk_usage', 5, 2)->nullable();
            $table->string('load_average')->nullable();
            $table->text('services')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('server_checks');
    }
};
