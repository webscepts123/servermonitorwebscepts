<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerDomain extends Model
{
    protected $fillable = [
        'server_id',
        'domain',
        'is_primary',
        'dns_provider',
        'active_dns_ip',
        'last_dns_update_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'last_dns_update_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}