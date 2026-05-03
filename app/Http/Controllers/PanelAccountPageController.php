<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Support\Facades\Schema;

class PanelAccountPageController extends Controller
{
    public function cpanel()
    {
        $servers = Server::query()
            ->when(Schema::hasColumn('servers', 'panel_type'), function ($query) {
                $query->where(function ($q) {
                    $q->where('panel_type', 'cpanel')
                        ->orWhere('panel_type', 'whm')
                        ->orWhereNull('panel_type');
                });
            })
            ->latest()
            ->get();

        return view('panel.cpanel', compact('servers'));
    }

    public function plesk()
    {
        $servers = Server::query()
            ->when(Schema::hasColumn('servers', 'panel_type'), function ($query) {
                $query->where('panel_type', 'plesk');
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->latest()
            ->get();

        return view('panel.plesk', compact('servers'));
    }

    public function wordpress()
    {
        $servers = Server::latest()->get();

        return view('panel.wordpress', compact('servers'));
    }
}