<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMonitorStatus extends Model
{
    protected $fillable = [
        'cpanel_account_id',
        'server_id',
        'account_name',
        'domain',
        'host',
        'website_up',
        'cpanel_up',
        'whm_up',
        'wordpress_up',
        'last_status',
        'last_error',
        'last_checked_at',
        'last_down_alert_sent_at',
        'last_recovery_alert_sent_at',
    ];

    protected $casts = [
        'website_up' => 'boolean',
        'cpanel_up' => 'boolean',
        'whm_up' => 'boolean',
        'wordpress_up' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_down_alert_sent_at' => 'datetime',
        'last_recovery_alert_sent_at' => 'datetime',
    ];
}