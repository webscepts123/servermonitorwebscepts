<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    protected $fillable = [
        'name',
        'host',
        'ssh_port',
        'username',
        'password',
        'is_active',
        'admin_email',
        'admin_phone',
        'customer_name',
        'customer_email',
        'customer_phone',
        'email_alerts_enabled',
        'sms_alerts_enabled',
        'backup_server_id',
        'panel_type',
        'disk_warning_percent',
        'disk_transfer_percent',
        'google_drive_remote',
        'backup_path',
        'google_drive_sync',
        'is_active',
        'website_url',
        'status',
        'local_backup_path',
        'sync_time',
        'auto_transfer',
        'google_drive_sync',
        'email_alerts_enabled',
        'sms_alerts_enabled',
        'last_down_alert_sent_at',
        'last_recovery_alert_sent_at',
        'last_known_status',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    public function checks()
{
    return $this->hasMany(ServerCheck::class);
}



    public function latestCheck()
    {
        return $this->hasOne(ServerCheck::class)->latestOfMany();
    }

    public function backupServer()
    {
        return $this->belongsTo(Server::class, 'backup_server_id');
    }

    public function securityAlerts()
    {
        return $this->hasMany(ServerSecurityAlert::class);
    }
}