<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerCheck extends Model
{
    protected $fillable = [
        'server_id',
        'online',
        'status',
        'cpu_usage',
        'ram_usage',
        'disk_usage',
        'load_average',
        'services',
        'ssh_online',
        'cpanel_online',
        'plesk_online',
        'website_online',
        'firewall_status',
        'security_alerts',
        'checked_at',
    ];

    protected $casts = [
        'online' => 'boolean',
        'ssh_online' => 'boolean',
        'cpanel_online' => 'boolean',
        'plesk_online' => 'boolean',
        'website_online' => 'boolean',
        'services' => 'array',
        'checked_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
