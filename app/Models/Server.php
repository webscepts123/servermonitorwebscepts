<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        /*
        |--------------------------------------------------------------------------
        | Basic Server Details
        |--------------------------------------------------------------------------
        */
        'name',
        'host',
        'website_url',
        'linked_domain',
        'panel_type',
        'is_active',
        'status',
        'last_status',
        'last_known_status',
        'last_error',
        'last_checked_at',

        /*
        |--------------------------------------------------------------------------
        | SSH Access
        |--------------------------------------------------------------------------
        */
        'ssh_port',
        'username',
        'password',
        'private_key',

        /*
        |--------------------------------------------------------------------------
        | WHM / cPanel API Access
        |--------------------------------------------------------------------------
        | WHM token is used first, password is fallback.
        |--------------------------------------------------------------------------
        */
        'whm_username',
        'whm_token',
        'whm_password',
        'whm_auth_type',
        'whm_port',
        'whm_ssl_verify',
        'whm_token_last_checked_at',
        'whm_token_status',
        'whm_token_error',

        /*
        |--------------------------------------------------------------------------
        | Main Alert Contacts
        |--------------------------------------------------------------------------
        */
        'admin_email',
        'admin_phone',
        'customer_name',
        'customer_email',
        'customer_phone',
        'alert_phones',
        'alert_emails',

        /*
        |--------------------------------------------------------------------------
        | Alert Settings
        |--------------------------------------------------------------------------
        */
        'email_alerts_enabled',
        'sms_alerts_enabled',
        'monitor_website',
        'monitor_cpanel',
        'monitor_frameworks',
        'send_recovery_alert',
        'last_down_alert_sent_at',
        'last_recovery_alert_sent_at',

        /*
        |--------------------------------------------------------------------------
        | Backup / Failover Settings
        |--------------------------------------------------------------------------
        */
        'backup_server_id',
        'disk_warning_percent',
        'disk_transfer_percent',
        'google_drive_remote',
        'backup_path',
        'local_backup_path',
        'backup_selected_accounts',
        'auto_transfer',
        'google_drive_sync',
        'sync_time',
        'daily_sync_time',

        /*
        |--------------------------------------------------------------------------
        | DNS Failover
        |--------------------------------------------------------------------------
        */
        'failover_enabled',
        'dns_failover_enabled',
        'last_failover_at',
        'last_failover_reason',
        'original_ip',
        'active_dns_ip',
    ];

    protected $casts = [
        /*
        |--------------------------------------------------------------------------
        | Booleans
        |--------------------------------------------------------------------------
        */
        'is_active' => 'boolean',

        'email_alerts_enabled' => 'boolean',
        'sms_alerts_enabled' => 'boolean',

        'monitor_website' => 'boolean',
        'monitor_cpanel' => 'boolean',
        'monitor_frameworks' => 'boolean',
        'send_recovery_alert' => 'boolean',

        'auto_transfer' => 'boolean',
        'google_drive_sync' => 'boolean',
        'failover_enabled' => 'boolean',
        'dns_failover_enabled' => 'boolean',
        'whm_ssl_verify' => 'boolean',

        /*
        |--------------------------------------------------------------------------
        | Arrays / JSON
        |--------------------------------------------------------------------------
        */
        'backup_selected_accounts' => 'array',

        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */
        'last_checked_at' => 'datetime',
        'last_failover_at' => 'datetime',
        'last_down_alert_sent_at' => 'datetime',
        'last_recovery_alert_sent_at' => 'datetime',
        'whm_token_last_checked_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'private_key',
        'whm_token',
        'whm_password',
    ];

    /*
    |--------------------------------------------------------------------------
    | Server Monitoring Checks
    |--------------------------------------------------------------------------
    */

    public function checks()
    {
        return $this->hasMany(ServerCheck::class);
    }

    public function latestCheck()
    {
        return $this->hasOne(ServerCheck::class)->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Backup Server
    |--------------------------------------------------------------------------
    */

    public function backupServer()
    {
        return $this->belongsTo(Server::class, 'backup_server_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Security Alerts
    |--------------------------------------------------------------------------
    */

    public function securityAlerts()
    {
        return $this->hasMany(ServerSecurityAlert::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Domains
    |--------------------------------------------------------------------------
    */

    public function domains()
    {
        return $this->hasMany(\App\Models\ServerDomain::class);
    }

    /*
    |--------------------------------------------------------------------------
    | cPanel Alert Contacts
    |--------------------------------------------------------------------------
    */

    public function cpanelAlertContacts()
    {
        return $this->hasMany(\App\Models\CpanelAlertContact::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getWhmHostAttribute()
    {
        return $this->host;
    }

    public function getWhmUsernameValueAttribute()
    {
        return $this->whm_username ?: $this->username ?: 'root';
    }

    public function getWhmPortValueAttribute()
    {
        return $this->whm_port ?: 2087;
    }

    public function hasWhmToken(): bool
    {
        return !empty($this->whm_token);
    }

    public function hasWhmPassword(): bool
    {
        return !empty($this->whm_password) || !empty($this->password);
    }

    public function alertPhonesArray(): array
    {
        $phones = [];

        foreach ([
            $this->admin_phone,
            $this->customer_phone,
            $this->alert_phones,
        ] as $value) {
            if (!$value) {
                continue;
            }

            foreach (explode(',', $value) as $phone) {
                $phone = trim($phone);

                if ($phone && !in_array($phone, $phones, true)) {
                    $phones[] = $phone;
                }
            }
        }

        return $phones;
    }

    public function alertEmailsArray(): array
    {
        $emails = [];

        foreach ([
            $this->admin_email,
            $this->customer_email,
            $this->alert_emails,
        ] as $value) {
            if (!$value) {
                continue;
            }

            foreach (explode(',', $value) as $email) {
                $email = trim($email);

                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL) && !in_array($email, $emails, true)) {
                    $emails[] = $email;
                }
            }
        }

        return $emails;
    }
}