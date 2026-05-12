<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PasskeyPageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $passkeys = method_exists($user, 'passkeys')
            ? $user->passkeys()->latest()->get()
            : collect();

        return view('profile.passkeys', compact('user', 'passkeys'));
    }
}