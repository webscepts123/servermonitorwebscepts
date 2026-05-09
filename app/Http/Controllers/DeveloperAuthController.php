<?php

namespace App\Http\Controllers;

use App\Models\DeveloperUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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

        $developer = DeveloperUser::where(function ($query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('contact_email', $login)
                    ->orWhere('cpanel_username', $login)
                    ->orWhere('username', $login);
            })
            ->first();

        if (!$developer) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Developer account not found.');
        }

        if (!$developer->is_active) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Developer account is disabled.');
        }

        $storedPassword = $developer->password;
        $passwordMatched = false;

        /*
        |--------------------------------------------------------------------------
        | Check hashed password
        |--------------------------------------------------------------------------
        | Normal Laravel password should be saved using Hash::make().
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
        | Temporary legacy plain password support
        |--------------------------------------------------------------------------
        | If old records have plain password, allow login once and convert to hash.
        | After this, password will be stored safely as hashed password.
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

        $developer->update([
            'last_login_at' => now(),
        ]);

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
}