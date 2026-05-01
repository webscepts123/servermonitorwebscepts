<?php

namespace App\Http\Controllers;

use App\Models\ServerSecurityAlert;

class SecurityController extends Controller
{
    public function alerts()
    {
        $alerts = ServerSecurityAlert::latest()->paginate(20);
        return view('security.alerts', compact('alerts'));
    }

    public function firewall()
    {
        return view('security.firewall');
    }

    public function abuse()
    {
        return view('security.abuse');
    }

    public function email()
    {
        return view('security.email');
    }

    public function ssh()
    {
        return view('security.ssh');
    }
}