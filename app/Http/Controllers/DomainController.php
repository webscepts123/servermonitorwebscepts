<?php

namespace App\Http\Controllers;

use App\Models\Server;

class DomainController extends Controller
{
    public function index()
    {
        // assuming domains are linked to servers
        $servers = Server::with('checks')->get();

        return view('domains.index', compact('servers'));
    }
}