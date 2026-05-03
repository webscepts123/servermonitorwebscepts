@extends('layouts.app')

@section('page-title', 'Servers')

@section('content')

@php
    use Illuminate\Support\Carbon;

    $servers = $servers ?? collect();

    $totalServers = $servers->count();

    $onlineServers = $servers->filter(function ($server) {
        return $server->latestCheck && $server->latestCheck->online;
    })->count();

    $offlineServers = $totalServers - $onlineServers;

    $lastCheck = $servers
        ->pluck('latestCheck.checked_at')
        ->filter()
        ->sortDesc()
        ->first();

    $activeServers = $servers->filter(fn ($server) => !empty($server->is_active))->count();

    $sentinelStats = [
        'encrypted_credentials' => $servers->filter(fn ($server) => !empty($server->password))->count(),
        'email_alerts' => $servers->filter(fn ($server) => !empty($server->email_alerts_enabled))->count(),
        'sms_alerts' => $servers->filter(fn ($server) => !empty($server->sms_alerts_enabled))->count(),
        'google_drive' => $servers->filter(fn ($server) => !empty($server->google_drive_sync))->count(),
        'backup_failover' => $servers->filter(fn ($server) => !empty($server->failover_enabled))->count(),
        'dns_failover' => $servers->filter(fn ($server) => !empty($server->dns_failover_enabled))->count(),
    ];

    $securityScore = 0;

    if ($totalServers > 0) {
        $securityScore += round(($sentinelStats['encrypted_credentials'] / max($totalServers, 1)) * 20);
        $securityScore += round(($sentinelStats['email_alerts'] / max($totalServers, 1)) * 10);
        $securityScore += round(($sentinelStats['sms_alerts'] / max($totalServers, 1)) * 10);
        $securityScore += round(($sentinelStats['google_drive'] / max($totalServers, 1)) * 15);
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

    $securityColor = match (true) {
        $securityScore >= 85 => 'text-green-600',
        $securityScore >= 65 => 'text-blue-600',
        $securityScore >= 40 => 'text-yellow-600',
        default => 'text-red-600',
    };

    $securityBar = match (true) {
        $securityScore >= 85 => 'from-green-500 to-emerald-600',
        $securityScore >= 65 => 'from-blue-500 to-cyan-600',
        $securityScore >= 40 => 'from-yellow-500 to-orange-500',
        default => 'from-red-500 to-orange-600',
    };

    $statusBadge = function ($online) {
        return $online
            ? 'bg-green-100 text-green-700 border-green-200'
            : 'bg-red-100 text-red-700 border-red-200';
    };

    $usageColor = function ($value, $normal = 'bg-blue-600') {
        $value = (float) $value;

        if ($value >= 90) {
            return 'bg-red-600';
        }

        if ($value >= 75) {
            return 'bg-orange-500';
        }

        if ($value >= 60) {
            return 'bg-yellow-500';
        }

        return $normal;
    };

    $frameworkModules = [
        ['name' => 'WordPress', 'icon' => 'fa-brands fa-wordpress', 'color' => 'bg-blue-100 text-blue-700', 'checks' => ['wp-config', 'plugins', 'XML-RPC']],
        ['name' => 'Laravel', 'icon' => 'fa-brands fa-laravel', 'color' => 'bg-red-100 text-red-700', 'checks' => ['.env', 'APP_DEBUG', 'composer']],
        ['name' => 'Angular', 'icon' => 'fa-brands fa-angular', 'color' => 'bg-purple-100 text-purple-700', 'checks' => ['source maps', 'env files']],
        ['name' => 'Node.js', 'icon' => 'fa-brands fa-node-js', 'color' => 'bg-green-100 text-green-700', 'checks' => ['package.json', '.env', 'API']],
        ['name' => 'PHP', 'icon' => 'fa-brands fa-php', 'color' => 'bg-indigo-100 text-indigo-700', 'checks' => ['phpinfo', 'config.php']],
        ['name' => 'Database', 'icon' => 'fa-solid fa-database', 'color' => 'bg-orange-100 text-orange-700', 'checks' => ['MySQL', 'PostgreSQL', 'SQL dumps']],
    ];
@endphp

<div class="space-y-6">

    {{-- SESSION ALERTS --}}
    @if(session('success'))
        <div class="bg-green-100 border border-green-300 text-green-800 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-300 text-red-800 rounded-2xl p-4 font-semibold">
            <i class="fa-solid fa-circle-exclamation mr-2"></i>{{ session('error') }}
        </div>
    @endif

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-blue-950 to-slate-900 p-7 text-white shadow-xl">
        <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-blue-500/20 blur-3xl"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-red-500/10 blur-3xl"></div>

        <div class="relative flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl lg:text-5xl font-black tracking-tight">
                        Servers
                    </h1>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        <span class="w-2 h-2 rounded-full bg-green-400"></span>
                        Live Monitoring
                    </span>

                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-cyan-500/20 border border-cyan-400/40 text-cyan-100 text-xs font-bold">
                        <i class="fa-solid fa-shield-virus"></i>
                        Webscepts SentinelCore
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-5xl">
                    Enterprise server monitoring with SSH, cPanel, Plesk, firewall, LiteSpeed, web security scanning,
                    encrypted credentials, backup failover and domain failover protection.
                </p>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span class="px-4 py-2 rounded-full bg-blue-500/20 border border-blue-400/40 text-blue-100 text-xs font-bold">
                        Total: {{ $totalServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Online: {{ $onlineServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-red-500/20 border border-red-400/40 text-red-100 text-xs font-bold">
                        Offline: {{ $offlineServers }}
                    </span>

                    <span class="px-4 py-2 rounded-full bg-purple-500/20 border border-purple-400/40 text-purple-100 text-xs font-bold">
                        SentinelCore: {{ $securityScore }}%
                    </span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                @if(Route::has('servers.create'))
                    <a href="{{ route('servers.create') }}"
                       class="px-6 py-4 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-black text-center">
                        <i class="fa-solid fa-plus mr-2"></i>
                        Add Server
                    </a>
                @endif

                @if(Route::has('technology.index'))
                    <a href="{{ route('technology.index') }}"
                       class="px-6 py-4 rounded-2xl bg-red-600 hover:bg-red-700 text-white font-black text-center">
                        <i class="fa-solid fa-shield-virus mr-2"></i>
                        SentinelCore
                    </a>
                @endif

                @if(Route::has('technology.webscanner.index'))
                    <a href="{{ route('technology.webscanner.index') }}"
                       class="px-6 py-4 rounded-2xl bg-purple-600 hover:bg-purple-700 text-white font-black text-center">
                        <i class="fa-solid fa-magnifying-glass-chart mr-2"></i>
                        Smart Scan
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- TOP STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-bold">Total Servers</p>
                    <h3 class="text-4xl font-black mt-2">{{ $totalServers }}</h3>
                    <p class="text-xs text-slate-400 mt-2">Active monitoring: {{ $activeServers }}</p>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center">
                    <i class="fa-solid fa-server text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-bold">Online</p>
                    <h3 class="text-4xl font-black text-green-600 mt-2">{{ $onlineServers }}</h3>
                    <p class="text-xs text-slate-400 mt-2">Healthy servers</p>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-green-100 text-green-700 flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-bold">Offline</p>
                    <h3 class="text-4xl font-black text-red-600 mt-2">{{ $offlineServers }}</h3>
                    <p class="text-xs text-slate-400 mt-2">Needs attention</p>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-red-100 text-red-700 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow p-6 border border-slate-100 hover:shadow-xl transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-slate-500 text-sm font-bold">Last Check</p>
                    <h3 class="text-lg font-black mt-2">
                        {{ $lastCheck ? Carbon::parse($lastCheck)->diffForHumans() : 'No checks yet' }}
                    </h3>
                    <p class="text-xs text-slate-400 mt-2">
                        {{ $lastCheck ? Carbon::parse($lastCheck)->format('Y-m-d H:i:s') : 'Waiting for cron' }}
                    </p>
                </div>

                <div class="w-14 h-14 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center">
                    <i class="fa-solid fa-clock text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- SENTINELCORE TECHNOLOGY PANEL --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-red-950 to-blue-950 p-7 text-white shadow-xl">
        <div class="absolute -top-28 right-0 w-96 h-96 bg-red-500/20 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-28 left-0 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl"></div>

        <div class="relative grid grid-cols-1 xl:grid-cols-3 gap-6 items-center">
            <div class="xl:col-span-2">
                <div class="flex items-center gap-3 flex-wrap">
                    <h2 class="text-3xl lg:text-4xl font-black">
                        Webscepts SentinelCore
                    </h2>

                    <span class="px-4 py-2 rounded-full bg-green-500/20 border border-green-400/40 text-green-100 text-xs font-bold">
                        Smart Security Technology
                    </span>
                </div>

                <p class="text-slate-300 mt-3 max-w-4xl">
                    Python-powered security engine for WordPress, Laravel, Angular, Node.js, PHP, MySQL, PostgreSQL,
                    exposed files, SSL, headers, cPanel/Plesk accounts and backup failover.
                </p>

                <div class="mt-5 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Encrypted</p>
                        <p class="text-xl font-black">{{ $sentinelStats['encrypted_credentials'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Email</p>
                        <p class="text-xl font-black">{{ $sentinelStats['email_alerts'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">SMS</p>
                        <p class="text-xl font-black">{{ $sentinelStats['sms_alerts'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Drive</p>
                        <p class="text-xl font-black">{{ $sentinelStats['google_drive'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">Backup FO</p>
                        <p class="text-xl font-black">{{ $sentinelStats['backup_failover'] }}</p>
                    </div>

                    <div class="rounded-2xl bg-white/10 border border-white/10 p-3">
                        <p class="text-xs text-slate-300">DNS FO</p>
                        <p class="text-xl font-black">{{ $sentinelStats['dns_failover'] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white/10 border border-white/10 p-6">
                <p class="text-slate-300 font-bold">SentinelCore Score</p>

                <div class="flex items-end gap-2 mt-2">
                    <h3 class="text-6xl font-black">{{ $securityScore }}</h3>
                    <span class="text-2xl font-black mb-2">%</span>
                </div>

                <p class="font-black text-lg mt-2 {{ $securityColor }}">
                    {{ $securityLevel }}
                </p>

                <div class="mt-5 h-4 bg-white/10 rounded-full overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r {{ $securityBar }}"
                         style="width: {{ min($securityScore, 100) }}%"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- FRAMEWORK SECURITY --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 overflow-hidden">
        <div class="p-6 border-b flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Framework & Database Security</h2>
                <p class="text-slate-500 mt-1">
                    SentinelCore checks common web technologies for exposed files, debug pages, dependency leaks and database risks.
                </p>
            </div>

            <input type="text"
                   id="frameworkSearch"
                   oninput="filterCards('frameworkSearch', '.framework-card')"
                   placeholder="Search frameworks..."
                   class="w-full lg:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($frameworkModules as $module)
                <div class="framework-card rounded-3xl border border-slate-100 p-5 hover:shadow-lg transition">
                    <div class="flex items-start gap-4">
                        <div class="w-14 h-14 rounded-2xl {{ $module['color'] }} flex items-center justify-center shrink-0">
                            <i class="{{ $module['icon'] }} text-xl"></i>
                        </div>

                        <div>
                            <h3 class="font-black text-slate-900 text-lg">{{ $module['name'] }}</h3>
                            <p class="text-sm text-green-600 font-black mt-1">Scanner Ready</p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach($module['checks'] as $check)
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">
                                        {{ $check }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- SEARCH --}}
    <div class="bg-white rounded-3xl shadow border border-slate-100 p-5">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-slate-900">Server Inventory</h2>
                <p class="text-sm text-slate-500">
                    Live monitoring overview with SSH, cPanel, Plesk, firewall, security alerts and SentinelCore protection.
                </p>
            </div>

            <div class="flex flex-col md:flex-row gap-3">
                <input type="text"
                       id="serverSearch"
                       oninput="filterServerRows()"
                       placeholder="Search server, host, panel..."
                       class="w-full md:w-96 px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">

                <select id="statusFilter"
                        onchange="filterServerRows()"
                        class="px-4 py-3 rounded-2xl border outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Status</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>
    </div>

    {{-- SERVER TABLE --}}
    <div class="bg-white rounded-3xl shadow overflow-hidden border border-slate-100">
        <div class="overflow-x-auto">
            <table class="w-full text-left min-w-[1300px]">
                <thead class="bg-slate-100">
                    <tr>
                        <th class="p-4">Server</th>
                        <th class="p-4">Host</th>
                        <th class="p-4">Status</th>
                        <th class="p-4">Panels</th>
                        <th class="p-4">Usage</th>
                        <th class="p-4">Firewall</th>
                        <th class="p-4">SentinelCore</th>
                        <th class="p-4">Security</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($servers as $server)
                        @php
                            $check = $server->latestCheck;
                            $alerts = [];

                            if ($check && $check->security_alerts) {
                                $decodedAlerts = json_decode($check->security_alerts, true);
                                $alerts = is_array($decodedAlerts) ? $decodedAlerts : [];
                            }

                            $isOnline = $check && $check->online;
                            $rowStatus = $isOnline ? 'online' : 'offline';

                            $cpu = (float) ($check->cpu_usage ?? 0);
                            $ram = (float) ($check->ram_usage ?? 0);
                            $disk = (float) ($check->disk_usage ?? 0);

                            $serverScore = 0;
                            $serverScore += !empty($server->password) ? 20 : 0;
                            $serverScore += !empty($server->email_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->sms_alerts_enabled) ? 15 : 0;
                            $serverScore += !empty($server->google_drive_sync) ? 15 : 0;
                            $serverScore += !empty($server->failover_enabled) ? 15 : 0;
                            $serverScore += !empty($server->dns_failover_enabled) ? 20 : 0;

                            $serverLevel = match (true) {
                                $serverScore >= 85 => ['Enterprise', 'bg-green-100 text-green-700 border-green-200'],
                                $serverScore >= 60 => ['Protected', 'bg-blue-100 text-blue-700 border-blue-200'],
                                $serverScore >= 35 => ['Basic', 'bg-yellow-100 text-yellow-700 border-yellow-200'],
                                default => ['Risk', 'bg-red-100 text-red-700 border-red-200'],
                            };
                        @endphp

                        <tr class="server-row border-t hover:bg-slate-50 transition align-top"
                            data-search="{{ strtolower(($server->name ?? '').' '.($server->host ?? '').' '.($server->username ?? '').' '.($server->panel_type ?? '').' '.($server->website_url ?? '')) }}"
                            data-status="{{ $rowStatus }}">
                            <td class="p-4">
                                <div class="font-black text-slate-900">{{ $server->name }}</div>
                                <div class="text-xs text-slate-500 mt-1">
                                    SSH: {{ $server->username }}@{{ $server->host }}:{{ $server->ssh_port }}
                                </div>

                                @if(!empty($server->website_url))
                                    <a href="{{ $server->website_url }}"
                                       target="_blank"
                                       class="text-xs text-blue-600 hover:underline break-all">
                                        {{ $server->website_url }}
                                    </a>
                                @endif
                            </td>

                            <td class="p-4 text-slate-600">
                                <div class="font-bold">{{ $server->host }}</div>
                                <div class="text-xs text-slate-400 mt-1">
                                    Panel: {{ strtoupper($server->panel_type ?? 'AUTO') }}
                                </div>
                            </td>

                            <td class="p-4">
                                <span class="px-3 py-1 rounded-full border text-sm font-black {{ $statusBadge($isOnline) }}">
                                    {{ $isOnline ? 'Online' : 'Offline' }}
                                </span>

                                <div class="mt-2 text-xs text-slate-500">
                                    SSH:
                                    @if($check && $check->ssh_online)
                                        <span class="text-green-600 font-semibold">Connected</span>
                                    @else
                                        <span class="text-red-600 font-semibold">Failed</span>
                                    @endif
                                </div>

                                <div class="text-xs text-slate-500">
                                    {{ $check && $check->checked_at ? Carbon::parse($check->checked_at)->diffForHumans() : 'Not checked yet' }}
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="flex flex-wrap gap-2">
                                    @if($check && $check->cpanel_online)
                                        <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">cPanel</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 text-xs font-semibold">cPanel</span>
                                    @endif

                                    @if($check && $check->plesk_online)
                                        <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">Plesk</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg bg-slate-100 text-slate-500 text-xs font-semibold">Plesk</span>
                                    @endif

                                    @if($check && $check->website_online)
                                        <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-xs font-semibold">Website</span>
                                    @else
                                        <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-xs font-semibold">Website</span>
                                    @endif
                                </div>
                            </td>

                            <td class="p-4">
                                <div class="space-y-3 min-w-44">
                                    <div>
                                        <div class="flex justify-between text-xs mb-1 font-semibold">
                                            <span>CPU</span>
                                            <span>{{ $check->cpu_usage ?? '-' }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-2 {{ $usageColor($cpu, 'bg-blue-600') }} rounded-full"
                                                 style="width: {{ min($cpu, 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs mb-1 font-semibold">
                                            <span>RAM</span>
                                            <span>{{ $check->ram_usage ?? '-' }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-2 {{ $usageColor($ram, 'bg-purple-600') }} rounded-full"
                                                 style="width: {{ min($ram, 100) }}%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-xs mb-1 font-semibold">
                                            <span>Disk</span>
                                            <span>{{ $check->disk_usage ?? '-' }}%</span>
                                        </div>
                                        <div class="h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-2 {{ $usageColor($disk, 'bg-orange-500') }} rounded-full"
                                                 style="width: {{ min($disk, 100) }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                @if($check && $check->firewall_status)
                                    @if(str_contains(strtolower($check->firewall_status), 'active'))
                                        <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                                            Active
                                        </span>
                                    @else
                                        <span class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-700 text-xs font-semibold">
                                            {{ $check->firewall_status }}
                                        </span>
                                    @endif
                                @else
                                    <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-semibold">
                                        Unknown
                                    </span>
                                @endif
                            </td>

                            <td class="p-4">
                                <div class="space-y-2">
                                    <span class="inline-flex px-3 py-1 rounded-full border text-xs font-black {{ $serverLevel[1] }}">
                                        {{ $serverLevel[0] }} {{ $serverScore }}%
                                    </span>

                                    <div class="flex flex-wrap gap-1">
                                        @if(!empty($server->email_alerts_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-cyan-100 text-cyan-700 text-[10px] font-black">Email</span>
                                        @endif

                                        @if(!empty($server->sms_alerts_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-purple-100 text-purple-700 text-[10px] font-black">SMS</span>
                                        @endif

                                        @if(!empty($server->google_drive_sync))
                                            <span class="px-2 py-1 rounded-lg bg-green-100 text-green-700 text-[10px] font-black">Drive</span>
                                        @endif

                                        @if(!empty($server->failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-orange-100 text-orange-700 text-[10px] font-black">Backup FO</span>
                                        @endif

                                        @if(!empty($server->dns_failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-blue-100 text-blue-700 text-[10px] font-black">DNS FO</span>
                                        @endif

                                        @if(empty($server->email_alerts_enabled) && empty($server->sms_alerts_enabled) && empty($server->google_drive_sync) && empty($server->failover_enabled) && empty($server->dns_failover_enabled))
                                            <span class="px-2 py-1 rounded-lg bg-red-100 text-red-700 text-[10px] font-black">Setup Needed</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <td class="p-4">
                                @if(count($alerts))
                                    <div class="space-y-1">
                                        @foreach(array_slice($alerts, 0, 2) as $alert)
                                            <div class="text-xs text-red-700 bg-red-50 rounded-lg px-2 py-1">
                                                {{ $alert }}
                                            </div>
                                        @endforeach

                                        @if(count($alerts) > 2)
                                            <div class="text-xs text-slate-500">+{{ count($alerts) - 2 }} more</div>
                                        @endif
                                    </div>
                                @else
                                    <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                                        No alerts
                                    </span>
                                @endif
                            </td>

                            <td class="p-4 text-right">
                                <div class="flex flex-wrap justify-end gap-2 min-w-80">
                                    @if(Route::has('servers.show'))
                                        <a href="{{ route('servers.show', $server) }}"
                                           class="px-3 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold">
                                            View
                                        </a>
                                    @endif

                                    @if(Route::has('servers.checkNow'))
                                        <form method="POST" action="{{ route('servers.checkNow', $server) }}">
                                            @csrf
                                            <button class="px-3 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-bold">
                                                Check
                                            </button>
                                        </form>
                                    @endif

                                    @if(Route::has('technology.webscanner.index'))
                                        <a href="{{ route('technology.webscanner.index') }}"
                                           class="px-3 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-bold">
                                            Scan
                                        </a>
                                    @endif

                                    @if(Route::has('servers.terminal'))
                                        <a href="{{ route('servers.terminal', $server) }}"
                                           class="px-3 py-2 rounded-lg bg-slate-900 hover:bg-slate-700 text-white text-sm font-bold">
                                            Terminal
                                        </a>
                                    @endif

                                    @if(Route::has('servers.edit'))
                                        <a href="{{ route('servers.edit', $server) }}"
                                           class="px-3 py-2 rounded-lg bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-bold">
                                            Edit
                                        </a>
                                    @endif

                                    @if(Route::has('servers.destroy'))
                                        <form method="POST" action="{{ route('servers.destroy', $server) }}"
                                              onsubmit="return confirm('Delete this server?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-3 py-2 rounded-lg bg-red-700 hover:bg-red-800 text-white text-sm font-bold">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="p-10 text-center">
                                <div class="text-5xl mb-3">🖥️</div>
                                <h3 class="text-xl font-bold text-slate-700">No servers added yet</h3>
                                <p class="text-slate-500 mt-1">Add your first server to start monitoring.</p>

                                @if(Route::has('servers.create'))
                                    <a href="{{ route('servers.create') }}"
                                       class="inline-block mt-4 px-5 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700">
                                        Add Server
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function filterCards(inputId, cardSelector) {
    const input = document.getElementById(inputId);
    const value = input ? input.value.toLowerCase() : '';

    document.querySelectorAll(cardSelector).forEach(function(card) {
        card.style.display = card.innerText.toLowerCase().includes(value) ? '' : 'none';
    });
}

function filterServerRows() {
    const search = document.getElementById('serverSearch')?.value.toLowerCase() || '';
    const status = document.getElementById('statusFilter')?.value.toLowerCase() || '';

    document.querySelectorAll('.server-row').forEach(function(row) {
        const rowSearch = row.getAttribute('data-search') || row.innerText.toLowerCase();
        const rowStatus = row.getAttribute('data-status') || '';

        let show = rowSearch.includes(search);

        if (status) {
            show = show && rowStatus === status;
        }

        row.style.display = show ? '' : 'none';
    });
}
</script>

@endsection