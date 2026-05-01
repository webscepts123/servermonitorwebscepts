<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpanel_accounts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('server_id')->nullable();

            $table->string('username');
            $table->string('domain')->nullable();

            // future fields
            $table->text('cpanel_password')->nullable();
            $table->string('wp_path')->nullable();

            $table->timestamps();

            $table->foreign('server_id')
                  ->references('id')
                  ->on('servers')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpanel_accounts');
    }
};