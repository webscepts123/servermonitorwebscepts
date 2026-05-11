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
        Schema::create('cpanel_alert_contacts', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Server / cPanel Account Details
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('server_id')->nullable()->index();
            $table->string('server_name')->nullable();
            $table->string('server_host')->nullable();

            $table->string('cpanel_username')->index();
            $table->string('domain')->nullable()->index();
            $table->string('email')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Alert Contact Details
            |--------------------------------------------------------------------------
            */
            $table->string('admin_phone', 50)->nullable();
            $table->string('admin_email')->nullable();

            $table->string('customer_phone', 50)->nullable();
            $table->string('customer_email')->nullable();

            $table->text('alert_phones')->nullable();
            $table->text('alert_emails')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Monitoring Options
            |--------------------------------------------------------------------------
            */
            $table->boolean('monitor_website')->default(true);
            $table->boolean('monitor_cpanel')->default(true);
            $table->boolean('monitor_frameworks')->default(true);
            $table->boolean('send_recovery_alert')->default(true);

            /*
            |--------------------------------------------------------------------------
            | Monitoring Status
            |--------------------------------------------------------------------------
            */
            $table->string('last_status')->default('unknown')->index();
            $table->text('last_error')->nullable();

            $table->boolean('website_up')->nullable();
            $table->boolean('cpanel_up')->nullable();
            $table->boolean('whm_up')->nullable();
            $table->boolean('framework_issue')->default(false);

            $table->json('detected_platforms')->nullable();
            $table->json('critical_issues')->nullable();
            $table->json('warning_issues')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Alert History
            |--------------------------------------------------------------------------
            */
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_down_alert_sent_at')->nullable();
            $table->timestamp('last_recovery_alert_sent_at')->nullable();
            $table->timestamp('last_warning_alert_sent_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Unique Account Per Server
            |--------------------------------------------------------------------------
            */
            $table->unique(['server_id', 'cpanel_username'], 'cpanel_alert_server_user_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cpanel_alert_contacts');
    }
};