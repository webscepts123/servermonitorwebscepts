@extends('layouts.app')

@section('page-title', 'Dashboard')

@section('content')

@php
    $servers = $servers ?? collect();
    $latestChecks = $latestChecks ?? collect();

    $totalServers = $totalServers ?? $servers->count();
    $onlineServers = $onlineServers ?? $servers->where('status', 'online')->count();
    $offlineServers = $offlineServers ?? $servers->where('status', 'offline')->count();

    $sentinelStats = [
        'encrypted_credentials' => $servers->filter(fn ($server) => !empty($server->password))->count(),
        'dns_failover' => $servers->where('dns_failover_enabled', true)->count(),
        'backup_failover' => $servers->where('failover_enabled', true)->count(),
        'google_drive' => $servers->where('google_drive_sync', true)->count(),
        'sms_alerts' => $servers->where('sms_alerts_enabled', true)->count(),
        'email_alerts' => $servers->where('email_alerts_enabled', true)->count(),
    ];

    $securityScore = 0;

    if ($totalServers > 0) {
        $securityScore += round(($sentinelStats['encrypted_credentials'] / max($totalServers, 1)) * 25);
        $securityScore += round(($sentinelStats['email_alerts'] / max($totalServers, 1)) * 15);
        $securityScore += round(($sentinelStats['sms_alerts'] / max($totalServers, 1)) * 15);
        $securityScore += round(($sentinelStats['backup_failover'] / max($totalServers, 1)) * 20);
        $securityScore += round(($sentinelStats['dns_failover'] / max($totalServers, 1)) * 25);
    }

    $securityScore = min($securityScore, 100);

    $securityLevel = match (true) {
        $securityScore >= 85 => 'Enterprise',
        $securityScore >= 65 => 'Protected',
        $securityScore >= 40 => 'Basic',
        default => 'Needs Setup',
    };

    $securityClass = match (true) {
        $securityScore >= 85 => 'text-green-600',
        $securityScore >= 65 => 'text-blue-600',
        $securityScore >= 40 => 'text-yellow-600',
        default => 'text-red-600',
    };

    $sentinelModules = [
        [
            'title' => 'SentinelCore',
            'value' => $securityLevel,
            'icon' => 'fa-shield-virus',
            'color' => 'bg-red-100 text-red-700',
            'route' => Route::has('technology.index') ? route('technology.index') : null,
        ],
        [
            'title' => 'Web Scanner',
            'value' => 'Framework Scan',
            'icon' => 'fa-magnifying-glass-chart',
            'color' => 'bg-blue-100 text-blue-700',
            'route' => Route::has('technology.webscanner.index') ? route('technology.webscanner.index') : null,
        ],
        [
            'title' => 'Encryption Vault',
            'value' => 'File Shield',
            'icon' => 'fa-file-shield',
            'color' => 'bg-purple-100 text-purple-700',
            'route' => Route::has('technology.index') ? route('technology.index') : null,
        ],
        [
            'title' => 'DNS Failover',
            'value' => $sentinelStats['dns_failover'] . ' Active',
            'icon' => 'fa-globe',
            'color' => 'bg-green-100 text-green-700',
            'route' => Route::has('domains.index') ? route('domains.index') : null,
        ],
    ];
@endphp

<div class="space-y-6">

    {{-- TOP STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Total Servers</p>
            <h2 class="text-4xl font-black mt-2">{{ $totalServers }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Online</p>
            <h2 class="text-4xl font-black mt-2 text-green-600">{{ $onlineServers }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Offline</p>
            <h2 class="text-4xl font-black mt-2 text-red-600">{{ $offlineServers }}</h2>
        </div>

        <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
            <p class="text-slate-500 font-bold">Last Check</p>
            <h2 class="text-2xl font-black mt-2">
                {{ optional($servers->max('last_checked_at'))->diffForHumans() ?? 'N/A' }}
            </h2>
        </div>
    </div>

    {{-- SENTINELCORE HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-80 h-80 bg-blue-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-80 h-80 bg-red-500/10 rounded-full blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl lg:text-4xl font-black">
                        Webscepts SentinelCore
                    </h1>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <span class="inline-block w-2 h-2 rounded-full bg-green-400 mr-2"></span>
                        Security Technology Active
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    Enterprise protection for WordPress, Laravel, Angular, Node.js, PHP frameworks, MySQL,
                    PostgreSQL, encrypted server credentials, web scanning, customer file protection,
                    backup failover and ClouDNS domain failover.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        WordPress
                    </span>
                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        Laravel
                    </span>
                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        Angular
                    </span>
                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Node.js
                    </span>
                    <span class="px-4 py-2 rounded-full bg-orange-500/20 border border-orange-400/40 text-orange-100 text-xs font-bold">
                        MySQL / PostgreSQL
                    </span>
                    <span class="px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        Web Scanner
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                @if(Route::has('technology.index'))
                    <a href="{{ route('technology.index') }}"
                       class="px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black text-center">
                        <i class="fa-solid fa-shield-virus mr-2"></i>
                        Open SentinelCore
                    </a>
                @endif

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Web Scanner
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- SENTINELCORE MODULES --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        @foreach($sentinelModules as $module)
            <a href="{{ $module['route'] ?? '#' }}"
               class="bg-white rounded-3xl shadow border border-slate-100 p-6 hover:shadow-xl transition">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-500 font-bold">{{ $module['title'] }}</p>
                        <h2 class="text-2xl font-black mt-2">{{ $module['value'] }}</h2>
                    </div>

                    <div class="w-14 h-14 rounded-2xl {{ $module['color'] }} flex items-center justify-center">
                        <i class="fa-solid {{ $module['icon'] }} text-xl"></i>
                    </div>
                </div>
            </a>
        @endforeach
    </div>

    {{-- SECURITY SCORE --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-6">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5">
            <div>
                <h2 class="text-2xl font-black text-slate-900">SentinelCore Security Score</h2>
                <p class="text-slate-500 mt-1">
                    Based on encrypted credentials, SMS/email alerts, backup failover and DNS failover.
                </p>
            </div>

            <div class="text-left xl:text-right">
                <h3 class="text-5xl font-black {{ $securityClass }}">{{ $securityScore }}%</h3>
                <p class="font-black text-slate-700">{{ $securityLevel }}</p>
            </div>
        </div>

        <div class="mt-5 h-4 bg-slate-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-red-600 via-yellow-500 to-green-600"
                 style="width: {{ $securityScore }}%"></div>
        </div>

        <div class="mt-5 grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">Encrypted</p>
                <p class="text-xl font-black">{{ $sentinelStats['encrypted_credentials'] }}/{{ $totalServers }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">Email Alerts</p>
                <p class="text-xl font-black">{{ $sentinelStats['email_alerts'] }}/{{ $totalServers }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">SMS Alerts</p>
                <p class="text-xl font-black">{{ $sentinelStats['sms_alerts'] }}/{{ $totalServers }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">Backup Failover</p>
                <p class="text-xl font-black">{{ $sentinelStats['backup_failover'] }}/{{ $totalServers }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">DNS Failover</p>
                <p class="text-xl font-black">{{ $sentinelStats['dns_failover'] }}/{{ $totalServers }}</p>
            </div>
            <div class="rounded-2xl bg-slate-50 border p-4">
                <p class="text-xs text-slate-500 font-black">Google Drive</p>
                <p class="text-xl font-black">{{ $sentinelStats['google_drive'] }}/{{ $totalServers }}</p>
            </div>
        </div>
    </div>

    {{-- SERVERS TABLE --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Servers</h2>
                <p class="text-slate-500">
                    Live monitoring overview with SSH, cPanel, Plesk, firewall, SentinelCore and security alerts.
                </p>
            </div>

            @if(Route::has('servers.create'))
                <a href="{{ route('servers.create') }}"
                   class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                    + Add Server
                </a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="p-4 text-left">Server</th>
                        <th class="p-4 text-left">Host</th>
                        <th class="p-4 text-left">Status</th>
                        <th class="p-4 text-left">Panels</th>
                        <th class="p-4 text-left">Usage</th>
                        <th class="p-4 text-left">SentinelCore</th>
                        <th class="p-4 text-left">Security</th>
                        <th class="p-4 text-left">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers as $server)
                        @php
                            $cpu = $server->cpu_usage ?? $server->cpu ?? null;
                            $ram = $server->ram_usage ?? $server->ram ?? null;
                            $disk = $server->disk_usage ?? $server->disk ?? null;

                            $serverScore = 0;
                            $serverScore += !empty($server->password) ? 20 : 0;
                            $serverScore += !empty($server->email_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->sms_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->google_drive_sync) ? 15 : 0;
                            $serverScore += !empty($server->failover_enabled) ? 15 : 0;
                            $serverScore += !empty($server->dns_failover_enabled) ? 20 : 0;

                            $serverLevel = match (true) {
                                $serverScore >= 85 => ['Enterprise', 'bg-green-100 text-green-700'],
                                $serverScore >= 60 => ['Protected', 'bg-blue-100 text-blue-700'],
                                $serverScore >= 35 => ['Basic', 'bg-yellow-100 text-yellow-700'],
                                default => ['Risk', 'bg-red-100 text-red-700'],
                            };
                        @endphp

                        <tr class="border-t hover:bg-slate-50">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500">
                                    SSH: {{ $server->username ?? 'root' }}@{{ $server->host }}:{{ $server->ssh_port ?? 22 }}
                                </div>
                            </td>

                            <td class="p-4 font-semibold text-slate-600">
                                {{ $server->host }}
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full text-xs font-black {{ strtolower($server->status ?? '') === 'online' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ ucfirst($server->status ?? 'offline') }}
                                </span>

                                <div class="text-xs mt-2">
                                    <span class="{{ strtolower($server->ssh_status ?? 'connected') === 'connected' ? 'text-green-600' : 'text-red-600' }} font-bold">
                                        SSH: {{ ucfirst($server->ssh_status ?? 'Connected') }}
                                    </span>
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    <span class="px-3 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-black">
                                        cPanel
                                    </span>
                                    <span class="px-3 py-1 rounded-lg bg-slate-100 text-slate-600 text-xs font-black">
                                        Plesk
                                    </span>
                                    @if(!empty($server->website_url))
                                        <span class="px-3 py-1 rounded-lg bg-blue-100 text-blue-700 text-xs font-black">
                                            Website
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="p-4 min-w-[190px]">
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between text-xs">
                                            <span>CPU</span>
                                            <span>{{ $cpu !== null ? $cpu.'%' : '-%' }}</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-600 rounded-full" style="width: {{ min((float)($cpu ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs">
                                            <span>RAM</span>
                                            <span>{{ $ram !== null ? $ram.'%' : '-%' }}</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-purple-600 rounded-full" style="width: {{ min((float)($ram ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs">
                                            <span>Disk</span>
                                            <span>{{ $disk !== null ? $disk.'%' : '-%' }}</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full {{ (float)($disk ?? 0) >= 90 ? 'bg-red-600' : 'bg-orange-500' }} rounded-full"
                                                 style="width: {{ min((float)($disk ?? 0), 100) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="space-y-2">
                                    <span class="inline-flex px-3 py-1 rounded-full {{ $serverLevel[1] }} text-xs font-black">
                                        {{ $serverLevel[0] }} {{ $serverScore }}%
                                    </span>

                                    <div class="flex flex-wrap gap-1">
                                        @if(!empty($server->google_drive_sync))
                                            <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-[10px] font-black">Drive</span>
                                        @endif

                                        @if(!empty($server->failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-orange-100 text-orange-700 text-[10px] font-black">Backup FO</span>
                                        @endif

                                        @if(!empty($server->dns_failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-[10px] font-black">DNS FO</span>
                                        @endif

                                        @if(empty($server->google_drive_sync) && empty($server->failover_enabled) && empty($server->dns_failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-[10px] font-black">Setup Needed</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-black">
                                    No alerts
                                </span>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold">
                                            View
                                        </a>
                                    @endif

                                    @if(Route::has('servers.check'))
                                        <form method="POST" action="{{ route('servers.check', $server) }}">
                                            @csrf
                                            <button class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white font-bold">
                                                Check
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('technology.webscanner.index'))
                                        <a href="{{ route('technology.webscanner.index') }}"
                                           class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold">
                                            Scan
                                        </a>
                                    @endif

                                    @if(Route::has('servers.edit'))
                                        <a href="{{ route('servers.edit', $server) }}"
                                           class="px-4 py-2 rounded-xl bg-yellow-500 hover:bg-yellow-600 text-white font-bold">
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-10 text-center text-slate-500">
                                No servers found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection