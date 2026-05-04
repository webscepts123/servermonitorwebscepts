<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeveloperAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('developer')->check()) {
            return redirect()->route('developer.login');
        }

        $developer = Auth::guard('developer')->user();

        if (!$developer || !$developer->is_active) {
            Auth::guard('developer')->logout();

            return redirect()->route('developer.login')
                ->with('error', 'Developer account is disabled.');
        }

        return $next($request);
    }
}