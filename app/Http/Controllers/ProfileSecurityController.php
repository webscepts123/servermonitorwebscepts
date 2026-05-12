<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileSecurityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $passkeys = collect();

        if ($user && method_exists($user, 'passkeys')) {
            $passkeys = $user->passkeys()->latest()->get();
        }

        return view('profile.security', compact('user', 'passkeys'));
    }
}