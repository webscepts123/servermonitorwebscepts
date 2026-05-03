<?php

namespace App\Http\Controllers;

use App\Models\Server;

class PanelAccountPageController extends Controller
{
    public function cpanel()
    {
        $servers = Server::query()
            ->where(function ($query) {
                $query->where('panel_type', 'cpanel')
                    ->orWhere('panel_type', 'whm')
                    ->orWhereNull('panel_type');
            })
            ->latest()
            ->get();

        return view('panel.cpanel', compact('servers'));
    }

    public function plesk()
    {
        $servers = Server::query()
            ->where('panel_type', 'plesk')
            ->latest()
            ->get();

        return view('panel.plesk', compact('servers'));
    }

    public function wordpress()
    {
        $servers = Server::query()
            ->latest()
            ->get();

        return view('panel.wordpress', compact('servers'));
    }
}