<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class DeveloperUser extends Authenticatable
{
    use Notifiable;

    protected $table = 'developer_users';

    protected $fillable = [
        'name',
        'email',
        'cpanel_username',
        'contact_email',
        'cpanel_domain',
        'server_id',
        'password',
        'temporary_password',
        'password_must_change',
        'role',
        'ssh_username',
        'allowed_project_path',
        'can_git_pull',
        'can_clear_cache',
        'can_composer',
        'can_npm',
        'can_view_files',
        'can_edit_files',
        'can_delete_files',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'can_git_pull' => 'boolean',
        'can_clear_cache' => 'boolean',
        'can_composer' => 'boolean',
        'can_npm' => 'boolean',
        'can_view_files' => 'boolean',
        'can_edit_files' => 'boolean',
        'can_delete_files' => 'boolean',
        'is_active' => 'boolean',
        'password_must_change' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}