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
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);

        $developer = DeveloperUser::where('email', $login)
            ->orWhere('contact_email', $login)
            ->orWhere('cpanel_username', $login)
            ->first();

        if (!$developer || !$developer->is_active) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Developer account is disabled or not found.');
        }

        if (!Hash::check($data['password'], $developer->password)) {
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