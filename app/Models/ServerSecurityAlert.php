<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerSecurityAlert extends Model
{
    protected $fillable = [
        'server_id',
        'type',
        'level',
        'title',
        'message',
        'source_ip',
        'location',
        'is_resolved',
        'detected_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}