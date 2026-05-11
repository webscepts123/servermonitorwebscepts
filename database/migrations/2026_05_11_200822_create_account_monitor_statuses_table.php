<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_monitor_statuses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('cpanel_account_id')->nullable()->index();
            $table->unsignedBigInteger('server_id')->nullable()->index();

            $table->string('account_name')->nullable();
            $table->string('domain')->nullable();
            $table->string('host')->nullable();

            $table->boolean('website_up')->default(true);
            $table->boolean('cpanel_up')->default(true);
            $table->boolean('whm_up')->default(true);
            $table->boolean('wordpress_up')->default(true);

            $table->string('last_status')->default('up');
            $table->text('last_error')->nullable();

            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_down_alert_sent_at')->nullable();
            $table->timestamp('last_recovery_alert_sent_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_monitor_statuses');
    }
};