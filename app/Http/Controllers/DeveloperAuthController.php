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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $developer = DeveloperUser::where('email', $credentials['email'])->first();

        if (!$developer || !$developer->is_active) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Developer account is disabled or not found.');
        }

        if (!Hash::check($credentials['password'], $developer->password)) {
            return back()
                ->withInput($request->only('email'))
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