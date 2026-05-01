<?php

namespace App\Http\Controllers;

use App\Models\ServerCheck;
use App\Models\Server;


class ToolsController extends Controller
{
    public function terminalList()
    {
        $servers = Server::where('is_active', 1)->latest()->get();

        return view('tools.terminal-list', compact('servers'));
    }

    public function checks()
    {
        $checks = ServerCheck::with('server')
            ->latest()
            ->limit(100)
            ->get();

        return view('tools.checks', compact('checks'));
    }

    public function logs()
    {
        $checks = ServerCheck::with('server')
            ->latest()
            ->limit(200)
            ->get();

        return view('tools.logs', compact('checks'));
    }
}