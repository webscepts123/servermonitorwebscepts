<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpanelAlertContact extends Model
{
    protected $fillable = [
        'server_id',
        'server_name',
        'server_host',

        'cpanel_username',
        'domain',
        'email',

        'admin_phone',
        'admin_email',
        'customer_phone',
        'customer_email',
        'alert_phones',
        'alert_emails',

        'monitor_website',
        'monitor_cpanel',
        'monitor_frameworks',
        'send_recovery_alert',

        'last_status',
        'last_error',

        'website_up',
        'cpanel_up',
        'whm_up',
        'framework_issue',

        'detected_platforms',
        'critical_issues',
        'warning_issues',

        'last_checked_at',
        'last_down_alert_sent_at',
        'last_recovery_alert_sent_at',
        'last_warning_alert_sent_at',
    ];

    protected $casts = [
        'monitor_website' => 'boolean',
        'monitor_cpanel' => 'boolean',
        'monitor_frameworks' => 'boolean',
        'send_recovery_alert' => 'boolean',

        'website_up' => 'boolean',
        'cpanel_up' => 'boolean',
        'whm_up' => 'boolean',
        'framework_issue' => 'boolean',

        'detected_platforms' => 'array',
        'critical_issues' => 'array',
        'warning_issues' => 'array',

        'last_checked_at' => 'datetime',
        'last_down_alert_sent_at' => 'datetime',
        'last_recovery_alert_sent_at' => 'datetime',
        'last_warning_alert_sent_at' => 'datetime',
    ];
}