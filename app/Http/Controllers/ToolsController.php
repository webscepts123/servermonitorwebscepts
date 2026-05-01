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
        return view('tools.logs', [
            'servers' => \App\Models\Server::latest()->get(),
            'logs' => [],
            'securityCount' => 0,
            'cpanelCount' => 0,
            'emailCount' => 0,
            'appCount' => 0,
        ]);
    }

}