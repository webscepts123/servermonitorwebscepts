<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerCheck;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index()
    {
        $servers = Server::latest()->get();
    
        $latestChecksByServer = ServerCheck::with('server')
            ->latest()
            ->get()
            ->groupBy('server_id');
    
        $servers = $servers->map(function ($server) use ($latestChecksByServer) {
            $latestCheck = $latestChecksByServer->get($server->id)?->first();
    
            // Normalize status: Online, ONLINE, online, " online " all become online
            $status = strtolower(trim($server->status ?? ''));
    
            if (!$status && $latestCheck) {
                $status = strtolower(trim($latestCheck->status ?? ''));
            }
    
            if (!in_array($status, ['online', 'offline'])) {
                $status = 'offline';
            }
    
            $server->live_status = $status;
            $server->latest_check = $latestCheck;
    
            return $server;
        });
    
        $totalServers = $servers->count();
        $onlineServers = $servers->where('live_status', 'online')->count();
        $offlineServers = $servers->where('live_status', 'offline')->count();
    
        $latestChecks = ServerCheck::with('server')
            ->latest()
            ->limit(10)
            ->get();
    
        $hasResponseTime = Schema::hasColumn('server_checks', 'response_time');
    
        $avgResponseTime = null;
        $fastServers = collect();
        $slowServers = collect();
    
        if ($hasResponseTime) {
            $avgResponseTime = ServerCheck::whereNotNull('response_time')
                ->avg('response_time');
    
            $fastServers = ServerCheck::select('server_id', \DB::raw('AVG(response_time) as avg_speed'))
                ->whereNotNull('response_time')
                ->groupBy('server_id')
                ->orderBy('avg_speed', 'asc')
                ->with('server')
                ->limit(5)
                ->get();
    
            $slowServers = ServerCheck::select('server_id', \DB::raw('AVG(response_time) as avg_speed'))
                ->whereNotNull('response_time')
                ->groupBy('server_id')
                ->orderBy('avg_speed', 'desc')
                ->with('server')
                ->limit(5)
                ->get();
        }
    
        $enterpriseModules = [
            [
                'title' => 'Server Speed',
                'value' => $avgResponseTime ? round($avgResponseTime, 2) . ' ms' : 'N/A',
                'icon' => 'fa-gauge-high',
            ],
            [
                'title' => 'Cache System',
                'value' => 'Optimized',
                'icon' => 'fa-bolt',
            ],
            [
                'title' => 'Security Level',
                'value' => 'Enterprise',
                'icon' => 'fa-shield-halved',
            ],
            [
                'title' => 'Backup Engine',
                'value' => 'Active',
                'icon' => 'fa-cloud-arrow-up',
            ],
        ];
    
        return view('dashboard.index', compact(
            'servers',
            'totalServers',
            'onlineServers',
            'offlineServers',
            'latestChecks',
            'avgResponseTime',
            'fastServers',
            'slowServers',
            'enterpriseModules',
            'hasResponseTime'
        ));
    }
}