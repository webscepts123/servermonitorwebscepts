<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentinelWebScan extends Model
{
    protected $fillable = [
        'server_id',
        'url',
        'domain',
        'ip',
        'http_status',
        'response_time_ms',
        'ssl_valid',
        'ssl_expires_at',
        'detected_technologies',
        'security_headers',
        'missing_headers',
        'exposed_files',
        'database_risks',
        'framework_risks',
        'risk_score',
        'risk_level',
        'summary',
    ];

    protected $casts = [
        'ssl_valid' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'detected_technologies' => 'array',
        'security_headers' => 'array',
        'missing_headers' => 'array',
        'exposed_files' => 'array',
        'database_risks' => 'array',
        'framework_risks' => 'array',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}