<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeveloperCpanelImportController extends Controller
{
    public function index()
    {
        $servers = Server::latest()->get();

        $developers = DeveloperUser::latest()
            ->limit(100)
            ->get();

        return view('developers.cpanel-import', compact('servers', 'developers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'server_id' => 'required|exists:servers,id',
            'cpanel_username' => 'required|string|max:100',
            'contact_email' => 'required|email|max:255',
            'cpanel_domain' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:100',

            'can_git_pull' => 'nullable|boolean',
            'can_clear_cache' => 'nullable|boolean',
            'can_composer' => 'nullable|boolean',
            'can_npm' => 'nullable|boolean',
            'can_view_files' => 'nullable|boolean',
            'can_edit_files' => 'nullable|boolean',
            'can_delete_files' => 'nullable|boolean',
        ]);

        $temporaryPassword = Str::password(16);

        $developer = DeveloperUser::updateOrCreate(
            [
                'cpanel_username' => $data['cpanel_username'],
            ],
            [
                'server_id' => $data['server_id'],
                'name' => $data['name'] ?: $data['cpanel_username'],
                'email' => $data['contact_email'],
                'contact_email' => $data['contact_email'],
                'cpanel_domain' => $data['cpanel_domain'] ?? null,
                'password' => Hash::make($temporaryPassword),
                'temporary_password' => Crypt::encryptString($temporaryPassword),
                'password_must_change' => true,
                'role' => $data['role'] ?? 'developer',
                'ssh_username' => $data['cpanel_username'],
                'allowed_project_path' => base_path(),

                'can_git_pull' => $request->boolean('can_git_pull'),
                'can_clear_cache' => $request->boolean('can_clear_cache', true),
                'can_composer' => $request->boolean('can_composer'),
                'can_npm' => $request->boolean('can_npm'),
                'can_view_files' => $request->boolean('can_view_files', true),
                'can_edit_files' => $request->boolean('can_edit_files'),
                'can_delete_files' => $request->boolean('can_delete_files'),

                'is_active' => true,
            ]
        );

        return back()
            ->with('success', 'Developer login created from cPanel account.')
            ->with('created_login', [
                'name' => $developer->name,
                'login' => $developer->cpanel_username,
                'email' => $developer->email,
                'password' => $temporaryPassword,
                'url' => 'https://developercodes.webscepts.com/login',
            ]);
    }

    public function resetPassword(DeveloperUser $developer)
    {
        $temporaryPassword = Str::password(16);

        $developer->update([
            'password' => Hash::make($temporaryPassword),
            'temporary_password' => Crypt::encryptString($temporaryPassword),
            'password_must_change' => true,
        ]);

        return back()
            ->with('success', 'Temporary developer password reset.')
            ->with('created_login', [
                'name' => $developer->name,
                'login' => $developer->cpanel_username ?: $developer->email,
                'email' => $developer->email,
                'password' => $temporaryPassword,
                'url' => 'https://developercodes.webscepts.com/login',
            ]);
    }

    public function destroy(DeveloperUser $developer)
    {
        $developer->delete();

        return back()->with('success', 'Developer login deleted.');
    }
}