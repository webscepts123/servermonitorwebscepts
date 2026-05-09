<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DeveloperAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('developer')->check()) {
            return redirect()->route('developer.domain.workspace');
        }

        return view('developers.auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);
        $password = $data['password'];

        $developerTable = (new DeveloperUser())->getTable();

        /*
        |--------------------------------------------------------------------------
        | Find developer safely
        |--------------------------------------------------------------------------
        | Your table does not have username column, so we only use columns
        | that actually exist in developer_users table.
        |--------------------------------------------------------------------------
        */
        $developer = DeveloperUser::where(function ($query) use ($login, $developerTable) {
            if (Schema::hasColumn($developerTable, 'email')) {
                $query->orWhere('email', $login);
            }

            if (Schema::hasColumn($developerTable, 'contact_email')) {
                $query->orWhere('contact_email', $login);
            }

            if (Schema::hasColumn($developerTable, 'cpanel_username')) {
                $query->orWhere('cpanel_username', $login);
            }

            if (Schema::hasColumn($developerTable, 'username')) {
                $query->orWhere('username', $login);
            }
        })->first();

        if (!$developer) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Developer account not found.');
        }

        if (!$this->developerPortalIsActive($developer)) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Developer portal access is disabled.');
        }

        $storedPassword = $developer->password;
        $passwordMatched = false;

        /*
        |--------------------------------------------------------------------------
        | Check hashed Laravel password
        |--------------------------------------------------------------------------
        */
        if (!empty($storedPassword)) {
            try {
                if (Hash::check($password, $storedPassword)) {
                    $passwordMatched = true;
                }
            } catch (\Throwable $e) {
                $passwordMatched = false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Temporary plain password support
        |--------------------------------------------------------------------------
        | If any old developer password was saved as plain text,
        | login will work once and then convert to hashed password.
        |--------------------------------------------------------------------------
        */
        if (!$passwordMatched && !empty($storedPassword) && hash_equals($storedPassword, $password)) {
            $passwordMatched = true;

            $developer->password = Hash::make($password);
            $developer->save();
        }

        if (!$passwordMatched) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Invalid developer login details.');
        }

        Auth::guard('developer')->login($developer, $request->boolean('remember'));

        if (Schema::hasColumn($developerTable, 'last_login_at')) {
            $developer->update([
                'last_login_at' => now(),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('developer.domain.workspace');
    }

    public function logout(Request $request)
    {
        Auth::guard('developer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('developer.login')
            ->with('success', 'Developer logged out successfully.');
    }

    private function developerPortalIsActive(DeveloperUser $developer): bool
    {
        $developerTable = $developer->getTable();

        if (Schema::hasColumn($developerTable, 'developer_portal_access')) {
            return (bool) $developer->developer_portal_access;
        }

        if (Schema::hasColumn($developerTable, 'portal_access_enabled')) {
            return (bool) $developer->portal_access_enabled;
        }

        if (Schema::hasColumn($developerTable, 'developer_portal_enabled')) {
            return (bool) $developer->developer_portal_enabled;
        }

        if (Schema::hasColumn($developerTable, 'is_active')) {
            return (bool) $developer->is_active;
        }

        return true;
    }
}