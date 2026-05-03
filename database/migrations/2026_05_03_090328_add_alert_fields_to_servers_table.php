<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('website_url')->nullable()->after('host');

            $table->string('admin_email')->nullable();
            $table->string('admin_phone')->nullable();

            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();

            $table->boolean('email_alerts_enabled')->default(true);
            $table->boolean('sms_alerts_enabled')->default(false);

            $table->timestamp('last_down_alert_sent_at')->nullable();
            $table->timestamp('last_recovery_alert_sent_at')->nullable();

            $table->string('last_known_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'website_url',
                'admin_email',
                'admin_phone',
                'customer_name',
                'customer_email',
                'customer_phone',
                'email_alerts_enabled',
                'sms_alerts_enabled',
                'last_down_alert_sent_at',
                'last_recovery_alert_sent_at',
                'last_known_status',
            ]);
        });
    }
};