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

        'backup_server_id',
        'disk_warning_percent',
        'disk_transfer_percent',
        'google_drive_remote',
        'backup_path',
        'local_backup_path',
        'sync_time',
        'auto_transfer',
        'google_drive_sync',
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